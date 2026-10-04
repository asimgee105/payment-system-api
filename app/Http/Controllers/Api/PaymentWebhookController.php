<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentWebhookController extends Controller
{
    public function __invoke(
        Request $request
    ): JsonResponse {
        $payload = $request->getContent();

        $signature = $request->header(
            'X-Webhook-Signature'
        );

        $expected = hash_hmac(
            'sha256',
            $payload,
            config(
                'services.fake_payment.webhook_secret'
            )
        );

        if (
            ! is_string($signature) ||
            ! hash_equals(
                $expected,
                $signature
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        $data = $request->validate([
            'event_id' => [
                'required',
                'string',
            ],

            'type' => [
                'required',
                'string',
            ],

            'gateway_payment_id' => [
                'required',
                'string',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Duplicate webhook protection
        |--------------------------------------------------------------------------
        */

        $existing = WebhookEvent::query()
            ->where(
                'event_id',
                $data['event_id']
            )
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'duplicate' => true,
            ]);
        }

        DB::transaction(
            function () use (
                $data,
                $request
            ) {
                $event = WebhookEvent::create([
                    'gateway' => 'fake',

                    'event_id' => $data['event_id'],

                    'event_type' => $data['type'],

                    'payload' => $request->all(),
                ]);

                $payment = Payment::query()
                    ->where(
                        'gateway_payment_id',
                        $data[
                            'gateway_payment_id'
                        ]
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $payment) {
                    $event->update([
                        'processing_error' => 'Payment not found.',
                    ]);

                    return;
                }

                if (
                    $data['type'] ===
                    'payment.succeeded'
                ) {
                    $payment->update([
                        'status' => 'succeeded',

                        'failure_reason' => null,

                        'paid_at' => $payment->paid_at
                                ?? now(),
                    ]);

                    LedgerEntry::firstOrCreate(
                        [
                            'reference' => 'PAY-'.
                                $payment
                                    ->payment_reference,
                        ],
                        [
                            'payment_id' => $payment->id,

                            'type' => 'payment',

                            'amount' => $payment->amount,

                            'currency' => $payment->currency,

                            'description' => 'Payment confirmed by webhook',
                        ]
                    );
                }

                if (
                    $data['type'] ===
                    'payment.failed' &&
                    $payment->status !==
                    'succeeded'
                ) {
                    $payment->update([
                        'status' => 'failed',

                        'failure_reason' => 'Gateway webhook reported failure.',
                    ]);
                }

                $event->update([
                    'processed_at' => now(),
                ]);
            }
        );

        return response()->json([
            'success' => true,
            'duplicate' => false,
        ]);
    }
}
