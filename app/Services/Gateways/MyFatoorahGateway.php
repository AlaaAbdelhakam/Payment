<?php

namespace App\Services\Payments\Gateways;

use Illuminate\Support\Facades\Http;

class MyFatoorahGateway
{
    protected function baseUrl(): string
    {
        return config('payments.myfatoorah.is_test')
            ? 'https://apitest.myfatoorah.com'
            : 'https://api.myfatoorah.com';
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . config('payments.myfatoorah.api_key'),
            'Content-Type'  => 'application/json',
        ];
    }

    public function create(array $data): array
    {
        $payload = [
            'InvoiceValue'  => (float) $data['amount'],
            'CustomerName'  => $data['customer_name'],
            'CustomerEmail' => $data['customer_email'] ?? null,
            'CustomerMobile'=> $data['customer_mobile'] ?? null,
            'CallBackUrl'   => $data['success_url'],
            'ErrorUrl'      => $data['failure_url'],
        ];

        $response = Http::withHeaders($this->headers())
            ->post($this->baseUrl() . '/v2/ExecutePayment', $payload)
            ->throw()
            ->json();

        return [
            'gateway'     => 'myfatoorah',
            'reference'   => data_get($response, 'Data.PaymentId'),
            'payment_url' => data_get($response, 'Data.PaymentURL'),
            'raw'         => $response,
        ];
    }

    public function status(string $paymentId): array
    {
        $response = Http::withHeaders($this->headers())
            ->post($this->baseUrl() . '/v2/GetPaymentStatus', [
                'Key'     => $paymentId,
                'KeyType' => 'paymentid',
            ])
            ->throw()
            ->json();

        $invoiceStatus = strtolower(data_get($response, 'Data.InvoiceStatus', ''));

        $status = match (true) {
            str_contains($invoiceStatus, 'paid')    => 'paid',
            str_contains($invoiceStatus, 'pending') => 'pending',
            str_contains($invoiceStatus, 'fail')    => 'failed',
            default                                 => 'unknown',
        };

        return [
            'gateway'   => 'myfatoorah',
            'reference' => $paymentId,
            'status'    => $status,
            'raw'       => $response,
        ];
    }

    public function callback(array $payload): array
    {
        $paymentId = $payload['PaymentId'] ?? $payload['paymentId'] ?? null;

        if (! $paymentId) {
            return [
                'gateway' => 'myfatoorah',
                'status'  => 'failed',
                'message' => 'Missing PaymentId',
                'raw'     => $payload,
            ];
        }

        return $this->status($paymentId);
    }
}