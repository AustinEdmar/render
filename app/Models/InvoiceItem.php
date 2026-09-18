<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'order_item_id',
        'description',
        'product_code',
        'unit',
        'quantity',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'tax_rate',
        'tax_code',
        'tax_exemption_reason',
        'net_amount',
        'tax_amount',
        'gross_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity'         => 'decimal:3',
            'unit_price'       => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount'  => 'decimal:2',
            'tax_rate'         => 'integer',
            'net_amount'       => 'decimal:2',
            'tax_amount'       => 'decimal:2',
            'gross_amount'     => 'decimal:2',
        ];
    }

    // ─── Relacionamentos ───────────────────────────────────

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // FIX: relação em falta no model original
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
