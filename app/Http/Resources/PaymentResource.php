<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'provider' => $this->provider,
            'payment_method' => $this->payment_method,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'gateway_payment_id' => $this->gateway_payment_id,
            'invoice_id' => $this->invoice_id,
            'reference' => $this->reference,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'status' => $this->order->status,
                'total' => $this->order->total ?? null,
            ]),
        ];
    }
}