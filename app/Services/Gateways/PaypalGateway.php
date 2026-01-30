<?php

namespace App\Services\Payments\Gateways;

class PaypalGateway
{
    public function create(array $data): array
    {
        //just example you can add on new gateways in that repo.
        throw new \RuntimeException('PayPal gateway not implemented yet.');
    }

    public function callback(array $payload): array
    {
        //just example you can add on new gateways in that repo.
        throw new \RuntimeException('PayPal callback not implemented yet.');
    }
}