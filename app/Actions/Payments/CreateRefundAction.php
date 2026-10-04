<?php

namespace App\Actions\Payments;

use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateRefundAction
{
    public function execute(
        User $user,
        Payment $payment,
        array $data
    ): array {
        if ($payment->user_id !== $user->id) {
            abort(403);
        }

        if ($payment->status !== 'succeeded') {
            throw ValidationException::withMessages([
                'payment' => [
                    'Sirf successful payment refund ho sakti hai.',
                ],
            ]);
        }

        $existing = Refund::query()
            ->where('payment_id', $payment->id)
            ->where(
                'idempotency_key',
                $data['idempotency_key']
            )
            ->first();

        if ($existing) {
            return [
                'refund' => $existing,
                'duplicate' => true,
            ];
        }

        return DB::transaction(
            function () use (
                $payment,
                $data
            ) {
                $lockedPayment = Payment::query()
                    ->lockForUpdate()
                    ->findOrFail($payment->id);

                $alreadyRefunded = Refund::query()
                    ->where(
                        'payment_id',
                        $lockedPayment->id
                    )
                    ->where(
                        'status',
                        'succeeded'
                    )
                    ->sum('amount');

                $remaining =
                    $lockedPayment->amount -
                    $alreadyRefunded;

                if ($data['amount'] > $remaining) {
                    throw ValidationException::withMessages([
                        'amount' => [
                            'Refund amount remaining payment amount se zyada hai.',
                        ],
                    ]);
                }

                $refund = Refund::create([
                    'payment_id' => $lockedPayment->id,

                    'idempotency_key' => $data['idempotency_key'],

                    'amount' => $data['amount'],

                    'status' => 'succeeded',

                    'gateway_refund_id' => 'ref_'.Str::uuid(),

                    'refunded_at' => now(),
                ]);

                LedgerEntry::create([
                    'payment_id' => $lockedPayment->id,

                    'type' => 'refund',

                    'amount' => $refund->amount,

                    'currency' => $lockedPayment->currency,

                    'reference' => 'REF-'.$refund->id.'-'.Str::uuid(),

                    'description' => 'Payment refund',
                ]);

                return [
                    'refund' => $refund,
                    'duplicate' => false,
                ];
            }
        );
    }
}
