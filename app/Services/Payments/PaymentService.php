<?php

namespace App\Services\Payments;

use InvalidArgumentException;

class PaymentService
{
    protected function resolve(string $gateway)
    {
        $map = config('payments.map', []);

        if (! isset($map[$gateway])) {
            throw new InvalidArgumentException("Payment gateway [$gateway] not supported.");
        }

        $class = $map[$gateway];

        if (! class_exists($class)) {
            throw new InvalidArgumentException("Gateway class [$class] not found.");
        }

        return app($class); 
    }

    public function gateway(?string $gateway = null)
    {
        return $this->resolve($gateway ?: config('payments.default'));
    }


    public function create(string $gateway, array $payload): array
    {
        return $this->resolve($gateway)->create($payload);
    }

    public function status(string $gateway, string $reference): array
    {
        return $this->resolve($gateway)->status($reference);
    }

    public function callback(string $gateway, array $payload): array
    {
        return $this->resolve($gateway)->callback($payload);
    }
}