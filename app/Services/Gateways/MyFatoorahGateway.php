<?php

namespace App\Services\Gateways;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

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

    protected function currency(array $data): string
    {
        return strtoupper((string) ($data['currency'] ?? config('payments.currency', 'SAR')));
    }

    protected function assertCurrencyAllowed(string $currency): void
    {
        $allowed = config('payments.allowed_currencies.myfatoorah', []);

        if (! empty($allowed) && ! in_array($currency, $allowed, true)) {
            throw new InvalidArgumentException("Currency [$currency] is not allowed for myfatoorah.");
        }
    }

    protected function normalizeCurrency(?string $currency): ?string
    {
        if (! $currency) {
            return null;
        }

        $c = strtoupper(trim($currency));

        return match ($c) {
            'SR', 'SAR' => 'SAR',
            'KD', 'KWD' => 'KWD',
            default     => $c,
        };
    }

    protected function initiatePayment(float $amount, string $currency): array
    {
        return Http::withHeaders($this->headers())
            ->post($this->baseUrl() . '/v2/InitiatePayment', [
                'InvoiceAmount' => $amount,
                'CurrencyIso'   => $currency,
            ])
            ->throw()
            ->json();
    }

    protected function resolvePaymentMethodId(array $data, float $amount, string $currency): ?int
    {
        $requested = isset($data['payment_method_id'])
            ? (int) $data['payment_method_id']
            : (int) config('payments.myfatoorah.payment_method_id');

        $init = $this->initiatePayment($amount, $currency);
        $methods = (array) data_get($init, 'Data.PaymentMethods', []);

        if (empty($methods)) {
            return null;
        }

        if ($requested > 0) {
            foreach ($methods as $m) {
                if ((int) data_get($m, 'PaymentMethodId') === $requested) {
                    return $requested;
                }
            }
        }

        return (int) data_get($methods[0], 'PaymentMethodId');
    }

    public function create(array $data): array
    {
        $currency = $this->currency($data);
        $this->assertCurrencyAllowed($currency);

        $amount = (float) $data['amount'];

        $paymentMethodId = $this->resolvePaymentMethodId($data, $amount, $currency);

        if (! $paymentMethodId) {
            throw new InvalidArgumentException("No available payment methods for MyFatoorah (currency: $currency).");
        }

        $payload = [
            'InvoiceValue'       => $amount,
            'PaymentMethodId'    => $paymentMethodId,
            'CustomerName'       => $data['customer_name'],
            'CustomerEmail'      => $data['customer_email'] ?? null,
            'CustomerMobile'     => $data['customer_mobile'] ?? null,
            'CallBackUrl'        => $data['success_url'],
            'ErrorUrl'           => $data['failure_url'],
            'DisplayCurrencyIso' => $currency,
            'CustomerReference'  => (string) ($data['order_id'] ?? ($data['payment_id'] ?? null)),
            'MobileCountryCode'  => $data['mobile_country_code'] ?? null,
            'Language'           => $data['language'] ?? null,
        ];

        $payload = array_filter($payload, static fn ($v) => $v !== null);

        $response = Http::withHeaders($this->headers())
            ->post($this->baseUrl() . '/v2/ExecutePayment', $payload)
            ->throw()
            ->json();

        return [
            'gateway'           => 'myfatoorah',
            'reference'         => (string) data_get($response, 'Data.PaymentId'),
            'payment_url'       => data_get($response, 'Data.PaymentURL'),
            'currency'          => $currency,
            'amount'            => $amount,
            'payment_method_id' => $paymentMethodId,
            'raw'               => $response,
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

        $data = data_get($response, 'Data', []);
        $invoiceStatus = strtolower((string) data_get($data, 'InvoiceStatus', ''));

        $transactions = (array) data_get($data, 'InvoiceTransactions', []);

        $status = 'pending';

        if (str_contains($invoiceStatus, 'paid')) {
            $status = 'paid';
        } else {
            foreach ($transactions as $tx) {
                $txStatus = strtolower((string) data_get($tx, 'TransactionStatus', ''));

                if (str_contains($txStatus, 'fail')) {
                    $status = 'failed';
                    break;
                }

                if (str_contains($txStatus, 'succ') || str_contains($txStatus, 'paid')) {
                    $status = 'paid';
                    break;
                }
            }
        }

        $amount = null;
        $currency = null;

        if (! empty($transactions[0])) {
            $paidCurrency = data_get($transactions[0], 'PaidCurrency');
            $paidValue    = data_get($transactions[0], 'PaidCurrencyValue');

            if ($paidCurrency !== null && $paidValue !== null) {
                $currency = $this->normalizeCurrency((string) $paidCurrency);
                $amount   = (float) $paidValue;
            }
        }

        if ($amount === null) {
            $amount = data_get($data, 'InvoiceValue');
            if ($amount === null) {
                $amount = data_get($data, 'InvoiceDisplayValue');
            }
            $amount = $amount !== null ? (float) $amount : null;
        }

        if ($currency === null) {
            $currency =
                (! empty($transactions[0]) ? (data_get($transactions[0], 'Currency') ?? null) : null)
                ?? data_get($data, 'InvoiceCurrency')
                ?? data_get($data, 'InvoiceDisplayCurrencyIso')
                ?? data_get($data, 'CurrencyIso');

            $currency = $this->normalizeCurrency($currency ? (string) $currency : null);
        }

        return [
            'gateway'   => 'myfatoorah',
            'reference' => (string) $paymentId,
            'status'    => $status,
            'amount'    => $amount,
            'currency'  => $currency,
            'raw'       => $response,
        ];
    }

    public function callback(array $payload): array
    {
        $paymentId = $payload['PaymentId']
            ?? $payload['paymentId']
            ?? $payload['payment_id']
            ?? $payload['id']
            ?? $payload['Id']
            ?? null;

        if (! $paymentId) {
            return [
                'gateway' => 'myfatoorah',
                'status'  => 'failed',
                'message' => 'Missing PaymentId',
                'raw'     => $payload,
            ];
        }

        return $this->status((string) $paymentId);
    }
}