<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentsCallbackController extends Controller
{
    public function __construct(
        protected PaymentService $payments
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $gateway = strtolower(trim((string) $request->query('gateway', config('payments.default'))));

        $payload = $this->normalizePayload(array_merge($request->query(), $request->all()));

        $gatewayResult = $this->payments->callback($gateway, $payload);

        $reference = $gatewayResult['reference']
            ?? ($payload['reference'] ?? null)
            ?? ($payload['paymentId'] ?? null)
            ?? ($payload['PaymentId'] ?? null)
            ?? ($payload['id'] ?? null)
            ?? ($payload['Id'] ?? null);

        $newStatus = $this->normalizeStatus($gatewayResult['status'] ?? 'unknown');

        $payment = $this->resolvePayment($gateway, $reference, $payload, $gatewayResult);

        if (! $payment) {
            return response()->json([
                'status'    => false,
                'message'   => 'Payment record not found for this callback.',
                'gateway'   => $gateway,
                'reference' => $reference,
                'raw'       => $gatewayResult['raw'] ?? $payload,
            ], 404);
        }

        $oldStatus = strtolower((string) $payment->status);

        $mergedRaw = $this->mergeRaw($payment->raw, $gatewayResult['raw'] ?? $payload);

        $update = ['raw' => $mergedRaw];

        if (! empty($reference) && (empty($payment->reference) || $payment->reference !== (string) $reference)) {
            $update['reference'] = (string) $reference;
        }

        if (! empty($gatewayResult['currency'])) {
            $update['currency'] = strtoupper((string) $gatewayResult['currency']);
        }

        if (isset($gatewayResult['amount']) && $gatewayResult['amount'] !== null) {
            $update['amount'] = (float) $gatewayResult['amount'];
        }

        if ($this->canTransition($oldStatus, $newStatus) && $newStatus !== 'unknown') {
            $update['status'] = $newStatus;
        }

        $payment->update($update);
        $payment->refresh();

        $order = $payment->order;

        if ($payment->status === 'paid' && $order->status !== 'paid') {
            $order->update(['status' => 'paid']);
        }

        if ($payment->status === 'failed' && $order->status !== 'paid' && $order->status !== 'failed') {
            $order->update(['status' => 'failed']);
        }

        return response()->json([
            'status'         => true,
            'gateway'        => $gateway,
            'payment'        => $payment->load('order.customer', 'order.items.product'),
            'order'          => $payment->order->fresh()->load('customer', 'items.product', 'payments'),
            'gateway_result' => $gatewayResult,
        ]);
    }

    protected function resolvePayment(
        string $gateway,
        ?string $reference,
        array $payload,
        array $gatewayResult
    ): ?Payment {
        $paymentId = $payload['payment_id'] ?? null;

        if (! empty($paymentId)) {
            return Payment::query()
                ->where('id', (int) $paymentId)
                ->where('gateway', $gateway)
                ->first();
        }

        if (! empty($reference)) {
            $found = Payment::query()
                ->where('gateway', $gateway)
                ->where('reference', (string) $reference)
                ->latest('id')
                ->first();

            if ($found) {
                return $found;
            }
        }

        $orderId =
            ($payload['customer_reference'] ?? null)
            ?? ($payload['CustomerReference'] ?? null)
            ?? data_get($gatewayResult, 'raw.Data.CustomerReference');

        if (! empty($orderId)) {
            return Payment::query()
                ->where('gateway', $gateway)
                ->where('order_id', (int) $orderId)
                ->latest('id')
                ->first();
        }

        return null;
    }

    protected function normalizeStatus(string $status): string
    {
        $s = strtolower(trim($status));

        return match (true) {
            in_array($s, ['paid', 'success', 'successful'], true) => 'paid',
            str_contains($s, 'succ') => 'paid',
            in_array($s, ['failed', 'fail', 'error'], true) => 'failed',
            $s === 'pending' => 'pending',
            default => 'unknown',
        };
    }

    protected function canTransition(string $from, string $to): bool
    {
        $from = strtolower(trim($from));

        if (in_array($from, ['paid', 'failed'], true)) {
            return false;
        }

        return true;
    }

    protected function normalizePayload(array $payload): array
    {
        $normalized = [];

        foreach ($payload as $key => $value) {
            $cleanKey = str_replace('amp;', '', (string) $key);
            $normalized[$cleanKey] = $value;
        }

        return $normalized;
    }

    protected function mergeRaw($existing, $incoming): array
    {
        $existingArr = is_array($existing) ? $existing : [];
        $incomingArr = is_array($incoming) ? $incoming : [];

        return array_merge($existingArr, [
            '_last_callback' => $incomingArr,
        ]);
    }
}