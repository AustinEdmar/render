<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'initial_amount' => $this->initial_amount,
            'opened_at' => $this->opened_at,
            'orders' => OrdersResource::make($this->whenLoaded('orders')),
            //'payments' => PaymentsResource::make($this->whenLoaded('payments')),
            //'refunds' => RefundsResource::make($this->whenLoaded('refunds')),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
