<?php

use App\Http\Controllers\PaymentTestController;
use Illuminate\Support\Facades\Route;

Route::get(
    '/payment-test',
    PaymentTestController::class
)->name('payment.test');
