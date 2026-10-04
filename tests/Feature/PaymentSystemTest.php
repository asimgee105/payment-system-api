<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_payment(): void
    {
        $this->postJson('/api/payments', [
            'amount' => 1000,
            'currency' => 'USD',
        ], [
            'Idempotency-Key' => 'guest-1',
        ])->assertUnauthorized();
    }

    public function test_payment_can_be_created_successfully(): void
    {
        config([
            'services.fake_payment.mode' => 'success',
        ]);

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson(
            '/api/payments',
            [
                'amount' => 1050,
                'currency' => 'USD',
            ],
            [
                'Idempotency-Key' => 'payment-1',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('data.status', 'succeeded');

        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'amount' => 1050,
            'status' => 'succeeded',
        ]);

        $this->assertDatabaseCount(
            'ledger_entries',
            1
        );
    }

    public function test_same_idempotency_key_does_not_create_payment_twice(): void
    {
        config([
            'services.fake_payment.mode' => 'success',
        ]);

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $headers = [
            'Idempotency-Key' => 'same-payment-key',
        ];

        $body = [
            'amount' => 2000,
            'currency' => 'USD',
        ];

        $this
            ->postJson('/api/payments', $body, $headers)
            ->assertCreated();

        $this
            ->postJson('/api/payments', $body, $headers)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount(
            'payments',
            1
        );

        $this->assertDatabaseCount(
            'ledger_entries',
            1
        );
    }

    public function test_temporary_gateway_failure_is_retried(): void
    {
        config([
            'services.fake_payment.mode' => 'fail_once',
        ]);

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this
            ->postJson(
                '/api/payments',
                [
                    'amount' => 3000,
                    'currency' => 'USD',
                ],
                [
                    'Idempotency-Key' => 'retry-test',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.status',
                'succeeded'
            );

        $this->assertDatabaseCount(
            'payment_attempts',
            2
        );

        $this->assertDatabaseHas(
            'payment_attempts',
            [
                'attempt_number' => 1,
                'status' => 'failed',
            ]
        );

        $this->assertDatabaseHas(
            'payment_attempts',
            [
                'attempt_number' => 2,
                'status' => 'succeeded',
            ]
        );
    }

    public function test_declined_payment_is_not_retried(): void
    {
        config([
            'services.fake_payment.mode' => 'declined',
        ]);

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this
            ->postJson(
                '/api/payments',
                [
                    'amount' => 4000,
                    'currency' => 'USD',
                ],
                [
                    'Idempotency-Key' => 'declined-test',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.status',
                'failed'
            );

        $this->assertDatabaseCount(
            'payment_attempts',
            1
        );

        $this->assertDatabaseCount(
            'ledger_entries',
            0
        );
    }

    public function test_other_user_cannot_view_payment(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $payment = Payment::create([
            'user_id' => $owner->id,
            'payment_reference' => fake()->uuid(),
            'idempotency_key' => 'private-payment',
            'amount' => 1000,
            'currency' => 'USD',
            'status' => 'pending',
            'gateway' => 'fake',
        ]);

        Sanctum::actingAs($outsider);

        $this
            ->getJson('/api/payments/'.$payment->id)
            ->assertForbidden();
    }

    public function test_successful_payment_can_be_refunded(): void
    {
        config([
            'services.fake_payment.mode' => 'success',
        ]);

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $paymentResponse = $this->postJson(
            '/api/payments',
            [
                'amount' => 5000,
                'currency' => 'USD',
            ],
            [
                'Idempotency-Key' => 'refund-payment',
            ]
        );

        $paymentId =
            $paymentResponse->json('data.id');

        $this
            ->postJson(
                "/api/payments/{$paymentId}/refunds",
                [
                    'amount' => 2000,
                ],
                [
                    'Idempotency-Key' => 'refund-1',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.status',
                'succeeded'
            );

        $this->assertDatabaseHas(
            'refunds',
            [
                'payment_id' => $paymentId,
                'amount' => 2000,
                'status' => 'succeeded',
            ]
        );

        $this->assertDatabaseHas(
            'ledger_entries',
            [
                'payment_id' => $paymentId,
                'type' => 'refund',
                'amount' => 2000,
            ]
        );
    }

    public function test_over_refund_is_rejected(): void
    {
        config([
            'services.fake_payment.mode' => 'success',
        ]);

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $paymentResponse = $this->postJson(
            '/api/payments',
            [
                'amount' => 1000,
                'currency' => 'USD',
            ],
            [
                'Idempotency-Key' => 'over-refund-payment',
            ]
        );

        $paymentId =
            $paymentResponse->json('data.id');

        $this
            ->postJson(
                "/api/payments/{$paymentId}/refunds",
                [
                    'amount' => 1500,
                ],
                [
                    'Idempotency-Key' => 'over-refund-1',
                ]
            )
            ->assertUnprocessable();

        $this->assertDatabaseCount(
            'refunds',
            0
        );
    }

    public function test_same_refund_key_does_not_refund_twice(): void
    {
        config([
            'services.fake_payment.mode' => 'success',
        ]);

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $paymentResponse = $this->postJson(
            '/api/payments',
            [
                'amount' => 5000,
                'currency' => 'USD',
            ],
            [
                'Idempotency-Key' => 'duplicate-refund-payment',
            ]
        );

        $paymentId =
            $paymentResponse->json('data.id');

        $headers = [
            'Idempotency-Key' => 'same-refund',
        ];

        $body = [
            'amount' => 1000,
        ];

        $this
            ->postJson(
                "/api/payments/{$paymentId}/refunds",
                $body,
                $headers
            )
            ->assertCreated();

        $this
            ->postJson(
                "/api/payments/{$paymentId}/refunds",
                $body,
                $headers
            )
            ->assertOk()
            ->assertJsonPath(
                'duplicate',
                true
            );

        $this->assertDatabaseCount(
            'refunds',
            1
        );
    }

    public function test_invalid_webhook_signature_is_rejected(): void
    {
        $this
            ->postJson(
                '/api/webhooks/payment',
                [
                    'event_id' => 'evt-invalid',
                    'type' => 'payment.succeeded',
                    'gateway_payment_id' => 'pay_test',
                ],
                [
                    'X-Webhook-Signature' => 'wrong-signature',
                ]
            )
            ->assertUnauthorized();
    }

    public function test_duplicate_valid_webhook_is_only_processed_once(): void
    {
        config([
            'services.fake_payment.webhook_secret' => 'test-secret',
        ]);

        $user = User::factory()->create();

        $payment = Payment::create([
            'user_id' => $user->id,
            'payment_reference' => fake()->uuid(),
            'idempotency_key' => 'webhook-payment',
            'amount' => 2500,
            'currency' => 'USD',
            'status' => 'pending',
            'gateway' => 'fake',
            'gateway_payment_id' => 'pay_webhook_123',
        ]);

        $payload = [
            'event_id' => 'evt_123',
            'type' => 'payment.succeeded',
            'gateway_payment_id' => 'pay_webhook_123',
        ];

        $json = json_encode($payload);

        $signature = hash_hmac(
            'sha256',
            $json,
            'test-secret'
        );

        $sendWebhook = function () use (
            $json,
            $signature
        ) {
            return $this->call(
                'POST',
                '/api/webhooks/payment',
                [],
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/json',

                    'HTTP_ACCEPT' => 'application/json',

                    'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
                ],
                $json
            );
        };

        $sendWebhook()
            ->assertOk()
            ->assertJsonPath(
                'duplicate',
                false
            );

        $sendWebhook()
            ->assertOk()
            ->assertJsonPath(
                'duplicate',
                true
            );

        $this->assertDatabaseCount(
            'webhook_events',
            1
        );

        $this->assertSame(
            'succeeded',
            $payment->fresh()->status
        );
    }
}
