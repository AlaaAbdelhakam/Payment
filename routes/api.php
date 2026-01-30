<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\PaymentsCallbackController;
use App\Http\Controllers\Api\OrdersController;
use App\Http\Controllers\Api\PaymentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('login', [AuthController::class, 'login']);

Route::any('payments/callback', PaymentsCallbackController::class); // can i say here it is just in case needed not in myfatoorah case


Route::middleware('auth:sanctum')->group(function () {

    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    
    Route::apiResource('users', UserController::class);
    
    Route::get('orders', [OrdersController::class, 'index']);
    Route::get('orders/{order}', [OrdersController::class, 'show']);
    Route::post('orders', [OrdersController::class, 'store']);
    Route::put('orders/{order}', [OrdersController::class, 'update']);
    Route::delete('orders/{order}', [OrdersController::class, 'destroy']);
    Route::post('orders/{id}/restore', [OrdersController::class, 'restore']);
    Route::delete('orders/{id}/force', [OrdersController::class, 'forceDelete']);
    
    Route::post('checkout', [CheckoutController::class, 'checkout']);
    
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);              
        Route::get('{payment}', [PaymentController::class, 'show']);       
    });
    Route::get('orders/{order}/payments', [PaymentController::class, 'orderPayments']); 
    
});