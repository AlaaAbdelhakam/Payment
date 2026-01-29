<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CheckoutRequest;
use App\Services\Checkout\CheckoutService;

class CheckoutController extends Controller
{
    public function checkout(CheckoutRequest $request, CheckoutService $service)
    {
        $result = $service->checkout($request->validated());

        return response()->json($result, 201);
    }
}