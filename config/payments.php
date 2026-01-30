<?php

return [
    'default' => env('PAYMENT_GATEWAY', 'myfatoorah'),
    'currency' => env('PAYMENT_CURRENCY', 'SAR'),

    'map' => [
        'myfatoorah' => \App\Services\Gateways\MyFatoorahGateway::class,
        // 'stripe' => \App\Services\Payments\Gateways\StripeGateway::class,
        // 'paypal' => \App\Services\Payments\Gateways\PaypalGateway::class,
    ],
    'allowed_currencies' => [
        'myfatoorah' => ['SAR', 'KWD', 'AED', 'USD'],
        'paypal'     => ['USD', 'EUR', 'GBP', 'SAR', 'AED', 'KWD'],
    ],
    'myfatoorah' => [
        'api_key' => env('MYFATOORAH_API_KEY', 'SK_KWT_vVZlnnAqu8jRByOWaRPNId4ShzEDNt256dvnjebuyzo52dXjAfRx2ixW5umjWSUx'),
        'is_test' => env('MYFATOORAH_IS_TEST', true),
    ],
    // test card myfatoorah 5123450000000008-01/39-100
];