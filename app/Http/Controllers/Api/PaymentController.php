<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::query()
            ->with(['order']); 

        if ($request->filled('order_id')) {
            $query->where('order_id', $request->integer('order_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        $perPage = (int) ($request->input('per_page', 15));
        $perPage = max(1, min($perPage, 100));

        return PaymentResource::collection(
            $query->orderByDesc('id')->paginate($perPage)
        );
    }

    public function show(Payment $payment, Request $request)
    {
        if ($request->boolean('with_trashed')) {
            $payment = Payment::withTrashed()->with(['order'])->findOrFail($payment->id);
        } else {
            $payment->load(['order']);
        }

        return new PaymentResource($payment);
    }

    public function orderPayments(Order $order, Request $request)
    {
        if ($request->boolean('with_trashed')) {
            $order = Order::withTrashed()->findOrFail($order->id);
        }

        $query = $order->payments()->with(['order']);

        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        return PaymentResource::collection(
            $query->orderByDesc('id')->get()
        );
    }
}