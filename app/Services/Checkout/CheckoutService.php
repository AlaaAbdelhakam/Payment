<?php

namespace App\Services\Checkout;

use App\Models\Order;
use App\Models\Payment;
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

            $order = Order::with(['customer', 'items.product'])->findOrFail($data['order_id']);

            if ($order->items->isEmpty()) {
                abort(422, 'Order has no items.');
            }

            if ((float) $order->total <= 0) {
                abort(422, 'Order total must be greater than 0.');
            }

            if ($order->status === 'paid') {
                abort(422, 'Order is already paid.');
            }

            $customer = $order->customer;

            $currency = strtoupper((string) ($data['currency'] ?? $order->currency ?? config('payments.currency', 'SAR')));

            $payment = Payment::create([
                'order_id'  => $order->id,
                'gateway'   => $gateway,
                'amount'    => (float) $order->total,
                'currency'  => $currency,
                'status'    => 'pending',
            ]);

            $payload = [
                'order_id'   => $order->id,
                'payment_id' => $payment->id,
                'amount' => (float) $order->total,
                'currency' => $currency,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_mobile' => $customer->mobile,
                'success_url' => url("/api/payments/callback?gateway={$gateway}&payment_id={$payment->id}"),
                'failure_url' => url("/api/payments/callback?gateway={$gateway}&payment_id={$payment->id}"),
            ];

            if (! empty($order->payment_method_id)) {
                $payload['payment_method_id'] = (int) $order->payment_method_id;
            }

            $createResult = $this->payments->create($gateway, $payload);

            $payment->update([
                'reference'   => $createResult['reference'] ?? null,  
                'payment_url' => $createResult['payment_url'] ?? null,
                'raw'         => $createResult['raw'] ?? null,
                'currency'    => $createResult['currency'] ?? $payment->currency,
            ]);

            $payment->refresh();

            if (empty($payment->payment_url)) {
                abort(422, 'Payment gateway did not return a payment_url.');
            }

            if ($order->status !== 'pending') {
                $order->update(['status' => 'pending']);
            }

            return [
                'order' => $order->fresh()->load('items.product', 'customer'),
                'payment' => $payment,
                'reference' => $payment->reference,
                'payment_url' => $payment->payment_url,
                'gateway' => $gateway,
                'final_status' => $payment->status,
            ];
        });
    }
}