<?php

namespace App\Services\Checkout;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function __construct(
        protected PaymentService $payments
    ) {}

    public function checkout(array $data): array
    {
        $gateway = $data['gateway'] ?? config('payments.default');

        return DB::transaction(function () use ($data, $gateway) {

            $customer = Customer::findOrFail($data['customer_id']);

            $order = Order::create([
                'customer_id' => $customer->id,
                'status' => 'pending',
                'total' => 0,
            ]);

            $productIds = collect($data['items'])->pluck('product_id')->unique()->values()->all();

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            $total = 0;

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);

                if (! $product) {
                    abort(422, "Product {$item['product_id']} is not available.");
                }

                $qty = (int) $item['qty'];
                $unit = (float) $product->price;
                $line = $unit * $qty;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'unit_price' => $unit,
                    'line_total' => $line,
                ]);

                $total += $line;
            }

            $order->update(['total' => $total]);

            $payment = Payment::create([
                'order_id' => $order->id,
                'gateway' => $gateway,
                'amount' => $total,
                'status' => 'pending',
            ]);

            $createResult = $this->payments->create($gateway, [
                'amount' => $total,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_mobile' => $customer->mobile,
                'success_url' => url("/api/v1/payments/callback?gateway={$gateway}&order_id={$order->id}&payment_id={$payment->id}"),
                'failure_url' => url("/api/v1/payments/callback?gateway={$gateway}&order_id={$order->id}&payment_id={$payment->id}"),
            ]);

            $payment->update([
                'reference' => $createResult['reference'] ?? null,
                'payment_url' => $createResult['payment_url'] ?? null,
                'raw' => $createResult['raw'] ?? null,
            ]);

            $payment->refresh();

            if (empty($payment->payment_url)) {
                abort(422, 'Payment gateway did not return a payment_url.');
            }

            // NOTE:
            // We do NOT call status() immediately here.
            // Most gateways will be "pending" until the customer pays at payment_url.
            // You should update payment/order status in callback/webhook.

            return [
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'mobile' => $customer->mobile,
                ],
                'order' => Order::with('items.product')->find($order->id),
                'payment' => $payment,
                'reference' => $payment->reference,
                'payment_url' => $payment->payment_url,
                'gateway' => [
                    'name' => $gateway,
                    'create_response' => $createResult,
                ],
                'final_status' => $payment->status,
            ];
        });
    }
}