<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PaymentTestController extends Controller
{
    public function __invoke(): View
    {
        abort_unless(
            app()->environment('local'),
            404
        );

        return view('payment-test');
    }
}
