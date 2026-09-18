<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// FIX: model em falta — o controller criava RefundItem::create() sem este model existir
// Ficheiro: app/Models/RefundItem.php
class RefundItem extends Model
{
    protected $fillable = [
        'refund_id',
        'order_item_id',
        'product_id',
        'quantity',
        'unit_price',
        'iva_rate',
        'iva_amount',
        'subtotal',
        'total_with_iva',
    ];

    protected function casts(): array
    {
        return [
            'quantity'      => 'integer',
            'iva_rate'      => 'integer',
            'unit_price'    => 'decimal:2',
            'iva_amount'    => 'decimal:2',
            'subtotal'      => 'decimal:2',
            'total_with_iva' => 'decimal:2',
        ];
    }

    // ─── Relacionamentos ───────────────────────────────────

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
