<?php

namespace App\Services\Payments;

use InvalidArgumentException;

class PaymentService
{
    private const REQUIRED_METHODS = ['create', 'status', 'callback'];

    protected function resolve(string $gateway)
    {
        $gateway = strtolower(trim($gateway));

        $map = config('payments.map', []);

        if (! is_array($map)) {
            throw new InvalidArgumentException('payments.map must be an array.');
        }

        if (! isset($map[$gateway])) {
            throw new InvalidArgumentException("Payment gateway [{$gateway}] not supported.");
        }

        $class = $map[$gateway];

        if (! is_string($class) || $class === '') {
            throw new InvalidArgumentException("Invalid gateway class mapping for [{$gateway}].");
        }

        if (! class_exists($class)) {
            throw new InvalidArgumentException("Gateway class [{$class}] not found.");
        }

        $obj = app($class);

        foreach (self::REQUIRED_METHODS as $method) {
            if (! method_exists($obj, $method)) {
                throw new InvalidArgumentException("Gateway [{$class}] must implement method [{$method}].");
            }
        }

        return $obj;
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