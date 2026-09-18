<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesResource extends JsonResource
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
            'table_id' => $this->table_id,
            'user_id' => $this->user_id,
            'shift_id' => $this->shift_id,
            'status' => $this->status,
            'kitchen_status' => $this->kitchen_status,
            'iva' => $this->iva,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'opened_at' => $this->opened_at,
            'closed_at' => $this->closed_at,

            'items' => $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'status' => $item->status,
                    'iva_rate' => $item->iva_rate,
                    'iva_amount' => $item->iva_amount,
                    'subtotal' => $item->subtotal,
                    'total_with_iva' => $item->total_with_iva,

                    'product' => [
                        'id' => $item->product?->id,
                        'name' => $item->product?->name,
                        'description' => $item->product?->description,
                        'price' => $item->product?->price,
                        'iva' => $item->product?->iva,
                        'stock' => $item->product?->stock,
                        'category_id' => $item->product?->category_id,

                        // caminho original
                        'image_path' => $item->product?->image_path,

                        // URL completa
                        'image_url' => $item->product?->image_path
                            ? asset('storage/' . $item->product->image_path)
                            : null,
                    ],
                ];
            }),

            'tables' => $this->tables,

            'payments' => $this->payments,

            'refunds' => $this->refunds,

            'shift' => $this->shift,

            'user' => $this->user,
        ];
    }
}