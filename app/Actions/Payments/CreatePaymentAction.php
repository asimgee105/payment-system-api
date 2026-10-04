<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePaymentAction
{
    public function __construct(
        private ProcessPaymentAction $processor
    ) {}

    public function execute(
        User $user,
        array $data
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Pehle duplicate request check
        |--------------------------------------------------------------------------
        */

        $existing = Payment::query()
            ->where(
                'user_id',
                $user->id
            )
            ->where(
                'idempotency_key',
                $data['idempotency_key']
            )
            ->first();

        if ($existing) {
            return [
                'payment' => $existing,
                'duplicate' => true,
            ];
        }

        try {
            /*
            |--------------------------------------------------------------------------
            | Sirf local DB creation transaction mein
            |--------------------------------------------------------------------------
            |
            | External gateway request ko transaction ke andar nahi rakhna.
            |
            */

            $payment = DB::transaction(
                function () use (
                    $user,
                    $data
                ) {
                    return Payment::create([
                        'user_id' => $user->id,

                        'payment_reference' => (string) Str::uuid(),

                        'idempotency_key' => $data[
                                'idempotency_key'
                            ],

                        'amount' => $data['amount'],

                        'currency' => $data['currency'],

                        'status' => 'pending',

                        'gateway' => 'fake',
                    ]);
                }
            );
        } catch (QueryException $exception) {
            /*
            |--------------------------------------------------------------------------
            | Race condition mein DB unique constraint bachayegi
            |--------------------------------------------------------------------------
            */

            $existing = Payment::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->where(
                    'idempotency_key',
                    $data['idempotency_key']
                )
                ->first();

            if ($existing) {
                return [
                    'payment' => $existing,
                    'duplicate' => true,
                ];
            }

            throw $exception;
        }

        /*
        |--------------------------------------------------------------------------
        | Gateway ko transaction ke bahar call
        |--------------------------------------------------------------------------
        */

        $payment = $this->processor
            ->execute($payment);

        return [
            'payment' => $payment,
            'duplicate' => false,
        ];
    }
}
