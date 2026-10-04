<?php

namespace App\Http\Controllers\Api;

use App\Actions\Payments\CreateRefundAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRefundRequest;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

class RefundController extends Controller
{
    public function store(
        StoreRefundRequest $request,
        Payment $payment,
        CreateRefundAction $action
    ): JsonResponse {
        $result = $action->execute(
            $request->user(),
            $payment,
            $request->validated()
        );

        $refund = $result['refund'];

        return response()->json([
            'success' => true,

            'duplicate' => $result['duplicate'],

            'data' => [
                'id' => $refund->id,

                'payment_id' => $refund->payment_id,

                'amount' => $refund->amount,

                'status' => $refund->status,

                'gateway_refund_id' => $refund->gateway_refund_id,

                'refunded_at' => $refund->refunded_at
                    ?->toISOString(),
            ],
        ], $result['duplicate'] ? 200 : 201);
    }
}
