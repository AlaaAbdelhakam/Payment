<?php

return [
    'default' => env('PAYMENT_GATEWAY', 'myfatoorah'),

    'map' => [
        'myfatoorah' => \App\Services\Payments\Gateways\MyFatoorahGateway::class,
        // 'stripe' => \App\Services\Payments\Gateways\StripeGateway::class,
        // 'paypal' => \App\Services\Payments\Gateways\PaypalGateway::class,
    ],

    'myfatoorah' => [
        'api_key' => env('MYFATOORAH_API_KEY'),
        'is_test' => env('MYFATOORAH_IS_TEST', true),
    ],
];