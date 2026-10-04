<?php

namespace App\Http\Controllers\Api;

use App\Actions\Payments\CreatePaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): mixed
    {
        $payments = Payment::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->latest()
            ->paginate(10);

        return PaymentResource::collection(
            $payments
        )->additional([
            'success' => true,
        ]);
    }

    public function store(
        StorePaymentRequest $request,
        CreatePaymentAction $action
    ): JsonResponse {
        $result = $action->execute(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,

            'duplicate' => $result['duplicate'],

            'message' => $result['duplicate']
                    ? 'Ye payment request pehle process ho chuki hai.'
                    : 'Payment request process ho gayi hai.',

            'data' => new PaymentResource(
                $result['payment']
            ),
        ], $result['duplicate'] ? 200 : 201);
    }

    public function show(
        Request $request,
        Payment $payment
    ): JsonResponse {
        abort_unless(
            $payment->user_id ===
                $request->user()->id,
            403
        );

        return response()->json([
            'success' => true,

            'data' => new PaymentResource(
                $payment
            ),
        ]);
    }
}
