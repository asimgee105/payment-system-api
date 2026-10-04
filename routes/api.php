<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\RefundController;
use Illuminate\Support\Facades\Route;

Route::post(
    '/webhooks/payment',
    PaymentWebhookController::class
);
Route::post(
    '/register',
    [AuthController::class, 'register']
)->middleware('throttle:5,1');

Route::post(
    '/login',
    [AuthController::class, 'login']
)->middleware('throttle:5,1');

Route::middleware([
    'auth:sanctum',
    'throttle:60,1',
])->group(function () {

    Route::post(
        '/logout',
        [AuthController::class, 'logout']
    );

    Route::get(
        '/payments',
        [PaymentController::class, 'index']
    );

    Route::post(
        '/payments',
        [PaymentController::class, 'store']
    );
    Route::post(
        '/payments/{payment}/refunds',
        [RefundController::class, 'store']
    );

    Route::get(
        '/payments/{payment}',
        [PaymentController::class, 'show']
    );
});
