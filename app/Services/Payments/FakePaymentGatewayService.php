<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Str;

class FakePaymentGatewayService
{
    public function charge(
        Payment $payment,
        int $attempt
    ): array {
        $mode = config(
            'services.fake_payment.mode',
            'success'
        );

        $requestId = 'req_'.Str::uuid();

        /*
        |--------------------------------------------------------------------------
        | Card declined
        |--------------------------------------------------------------------------
        |
        | Is error ko retry nahi karna.
        |
        */

        if ($mode === 'declined') {
            return [
                'success' => false,
                'retryable' => false,
                'request_id' => $requestId,
                'gateway_payment_id' => null,
                'error' => 'Card declined.',
                'raw' => [
                    'code' => 'card_declined',
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Pehli attempt fail, doosri success
        |--------------------------------------------------------------------------
        */

        if (
            $mode === 'fail_once' &&
            $attempt === 1
        ) {
            return [
                'success' => false,
                'retryable' => true,
                'request_id' => $requestId,
                'gateway_payment_id' => null,
                'error' => 'Temporary gateway error.',
                'raw' => [
                    'code' => 'temporary_error',
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Har attempt fail
        |--------------------------------------------------------------------------
        */

        if ($mode === 'always_fail') {
            return [
                'success' => false,
                'retryable' => true,
                'request_id' => $requestId,
                'gateway_payment_id' => null,
                'error' => 'Gateway unavailable.',
                'raw' => [
                    'code' => 'gateway_unavailable',
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */

        return [
            'success' => true,
            'retryable' => false,
            'request_id' => $requestId,

            'gateway_payment_id' => 'pay_'.Str::uuid(),

            'error' => null,

            'raw' => [
                'status' => 'succeeded',
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ],
        ];
    }
}
