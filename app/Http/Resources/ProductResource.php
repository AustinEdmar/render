<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'iva' => (int) $this->iva,
            'barcode' => (int) $this->barcode,
            'stock' => (int) $this->stock,
            'image_url' => $this->image_path
                ? asset('storage/' . $this->image_path)
                : null,
            'category' => $this->whenLoaded('category'),
            'is_active' => (bool) $this->is_active,
            //'created_at' => $this->created_at,
        ];
    }
}
