<?php

namespace App\Actions\Payments;

use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Services\Payments\FakePaymentGatewayService;
use Illuminate\Support\Facades\DB;

class ProcessPaymentAction
{
    public function __construct(
        private FakePaymentGatewayService $gateway
    ) {}

    public function execute(
        Payment $payment
    ): Payment {
        /*
        |--------------------------------------------------------------------------
        | Already final state
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $payment->status,
                ['succeeded', 'failed'],
                true
            )
        ) {
            return $payment;
        }

        $maxAttempts = 3;

        for (
            $attemptNumber = 1;
            $attemptNumber <= $maxAttempts;
            $attemptNumber++
        ) {
            $attempt = PaymentAttempt::create([
                'payment_id' => $payment->id,
                'attempt_number' => $attemptNumber,
                'status' => 'processing',
            ]);

            $response = $this->gateway->charge(
                $payment,
                $attemptNumber
            );

            /*
            |--------------------------------------------------------------------------
            | Attempt success
            |--------------------------------------------------------------------------
            */

            if ($response['success']) {
                DB::transaction(
                    function () use (
                        $payment,
                        $attempt,
                        $response
                    ) {
                        $lockedPayment = Payment::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $payment->id
                            );

                        /*
                        |--------------------------------------------------------------------------
                        | Kisi aur process ne pehle success kar diya
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $lockedPayment->status ===
                            'succeeded'
                        ) {
                            return;
                        }

                        $lockedPayment->update([
                            'status' => 'succeeded',

                            'gateway_payment_id' => $response[
                                    'gateway_payment_id'
                                ],

                            'failure_reason' => null,

                            'paid_at' => now(),
                        ]);

                        $attempt->update([
                            'status' => 'succeeded',

                            'gateway_request_id' => $response['request_id'],

                            'gateway_response' => $response['raw'],
                        ]);

                        /*
                        |--------------------------------------------------------------------------
                        | Financial ledger
                        |--------------------------------------------------------------------------
                        */

                        LedgerEntry::firstOrCreate(
                            [
                                'reference' => 'PAY-'.
                                    $lockedPayment
                                        ->payment_reference,
                            ],
                            [
                                'payment_id' => $lockedPayment->id,

                                'type' => 'payment',

                                'amount' => $lockedPayment->amount,

                                'currency' => $lockedPayment->currency,

                                'description' => 'Payment received',
                            ]
                        );
                    }
                );

                return $payment->fresh();
            }

            /*
            |--------------------------------------------------------------------------
            | Attempt fail
            |--------------------------------------------------------------------------
            */

            $attempt->update([
                'status' => 'failed',

                'gateway_request_id' => $response['request_id'],

                'error_message' => $response['error'],

                'gateway_response' => $response['raw'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | Retry nahi ho sakti
            |--------------------------------------------------------------------------
            */

            if (! $response['retryable']) {
                $payment->update([
                    'status' => 'failed',

                    'failure_reason' => $response['error'],
                ]);

                return $payment->fresh();
            }

            /*
            |--------------------------------------------------------------------------
            | Last retry bhi fail
            |--------------------------------------------------------------------------
            */

            if ($attemptNumber === $maxAttempts) {
                $payment->update([
                    'status' => 'failed',

                    'failure_reason' => $response['error'],
                ]);

                return $payment->fresh();
            }
        }

        return $payment->fresh();
    }
}
