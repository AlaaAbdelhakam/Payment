<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOrderRequest;
use App\Http\Requests\Api\UpdateOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrdersController extends Controller
{
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $data = $request->validated();

        $order = DB::transaction(function () use ($data) {

            $currency = strtoupper((string) ($data['currency'] ?? config('payments.currency', 'SAR')));

            $paymentMethodId = isset($data['payment_method_id'])
                ? (int) $data['payment_method_id']
                : (int) config('payments.myfatoorah.payment_method_id');

            $order = Order::create([
                'customer_id'        => $data['customer_id'],
                'status'             => 'pending',
                'total'              => 0,
                'currency'           => $currency,
                'payment_method_id'  => $paymentMethodId > 0 ? $paymentMethodId : null,
            ]);

            $items = collect($data['items']);
            $productIds = $items->pluck('product_id')->unique()->values()->all();

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            $total = 0.0;

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);

                if (! $product) {
                    abort(422, "Product {$item['product_id']} is not available.");
                }

                $qty = (int) $item['qty'];
                $unit = (float) $product->price;
                $line = round($unit * $qty, 2);

                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $product->id,
                    'qty'        => $qty,
                    'unit_price' => $unit,
                    'line_total' => $line,
                ]);

                $total = round($total + $line, 2);
            }

            $order->update(['total' => $total]);

            return $order->fresh()->load('customer', 'items.product', 'payments');
        });

        return response()->json([
            'status' => true,
            'data'   => $order,
        ], 201);
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $data = $request->validated();

        if ($order->payments()->exists()) abort(422, 'Cannot update an order with payments.');

        $order = DB::transaction(function () use ($order, $data) {

            if (isset($data['customer_id'])) {
                $order->update(['customer_id' => $data['customer_id']]);
            }

            if (isset($data['currency'])) {
                $order->update(['currency' => strtoupper((string) $data['currency'])]);
            }

            if (isset($data['payment_method_id'])) {
                $pmId = (int) $data['payment_method_id'];
                $order->update(['payment_method_id' => $pmId > 0 ? $pmId : null]);
            }

            if (isset($data['items'])) {
                $order->items()->delete();

                $items = collect($data['items']);
                $productIds = $items->pluck('product_id')->unique()->values()->all();

                $products = Product::query()
                    ->whereIn('id', $productIds)
                    ->where('is_active', true)
                    ->get()
                    ->keyBy('id');

                $total = 0.0;

                foreach ($data['items'] as $item) {
                    $product = $products->get($item['product_id']);

                    if (! $product) {
                        abort(422, "Product {$item['product_id']} is not available.");
                    }

                    $qty = (int) $item['qty'];
                    $unit = (float) $product->price;
                    $line = round($unit * $qty, 2);

                    OrderItem::create([
                        'order_id'   => $order->id,
                        'product_id' => $product->id,
                        'qty'        => $qty,
                        'unit_price' => $unit,
                        'line_total' => $line,
                    ]);

                    $total = round($total + $line, 2);
                }

                $order->update(['total' => $total]);
            }

            return $order->fresh()->load('customer', 'items.product', 'payments');
        });

        return response()->json([
            'status' => true,
            'data'   => $order,
        ]);
    }


    public function destroy(Order $order)
    {
        $hasPayments = $order->payments()->withTrashed()->exists();

        if ($hasPayments) {
            return response()->json([
                'status' => false,
                'message' => 'Order cannot be deleted because it has associated payments.',
            ], 422);
        }

        $order->delete();

        return response()->json([
            'status' => true,
            'message' => 'Order deleted successfully.',
        ]);
    }


    public function show(Order $order): JsonResponse
    {
        $order->load('customer', 'items.product', 'payments');

        return response()->json([
            'status' => true,
            'data' => $order,
        ]);
    }


    public function index(): JsonResponse
    {
        $query = Order::query()->with('customer', 'items.product', 'payments');

        if (request()->boolean('with_trashed')) {
            $query->withTrashed();
        }

        $orders = $query->latest('id')->paginate(15);

        return response()->json([
            'status' => true,
            'data' => $orders,
        ]);
    }


    public function restore(int $id): JsonResponse
    {
        $order = Order::withTrashed()->findOrFail($id);

        if (! $order->trashed()) {
            return response()->json([
                'status' => false,
                'message' => 'Order is not deleted.',
            ], 422);
        }

        DB::transaction(function () use ($order) {
            $order->restore();

            $order->items()->withTrashed()->restore();
        });

        return response()->json([
            'status' => true,
            'message' => 'Order restored successfully.',
            'data' => $order->fresh()->load('customer', 'items.product', 'payments'),
        ]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        //for root use if needed
        $order = Order::withTrashed()->findOrFail($id);

        if ($order->payments()->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot permanently delete order because payments are associated.',
            ], 422);
        }

        DB::transaction(function () use ($order) {
            $order->items()->withTrashed()->forceDelete();
            $order->forceDelete();
        });

        return response()->json([
            'status' => true,
            'message' => 'Order permanently deleted successfully.',
        ]);
    }
}