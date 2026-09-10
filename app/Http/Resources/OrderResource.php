<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'shift_id' => $this->shift_id,
            'customer_id' => $this->customer_id,
            'table_id' => $this->table_id,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'iva' => $this->iva,
            'discount' => $this->discount,
            'total' => $this->total,
            'opened_at' => $this->opened_at,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at,



            // Itens com produto e imagem resolvida
            'items' => $this->whenLoaded(
                'items',
                fn() =>
                $this->items->map(fn($item) => [
                    'id' => $item->id,
                    'order_id' => $item->order_id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'iva_rate' => $item->iva_rate,
                    'iva_amount' => $item->iva_amount,
                    'subtotal' => $item->subtotal,
                    'total_with_iva' => $item->total_with_iva,
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'description' => $item->product->description,
                        'price' => $item->product->price,
                        'iva' => $item->product->iva,
                        'stock' => $item->product->stock,
                        // URL completa igual ao CategoryResource
                        'image_path' => $item->product->image_path
                            ? asset('storage/' . $item->product->image_path)
                            : null,
                    ] : null,
                ])
            ),
        ];
    }
}