<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'reference' => $this->payment_reference,

            'amount' => $this->amount,

            'formatted_amount' => number_format(
                $this->amount / 100,
                2
            ),

            'currency' => $this->currency,

            'status' => $this->status,

            'gateway' => $this->gateway,

            'gateway_payment_id' => $this->gateway_payment_id,

            'failure_reason' => $this->failure_reason,

            'paid_at' => $this->paid_at?->toISOString(),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
