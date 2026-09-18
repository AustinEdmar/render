<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_code',
        'unit',
        'quantity',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'iva_rate',
        'tax_code',
        'tax_exemption_reason',
        'iva_amount',
        'subtotal',
        'total_with_iva',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'double', // <--- Mude aqui
            'discount_percent' => 'double',
            'discount_amount' => 'double',
            'iva_rate' => 'integer',
            'iva_amount' => 'double',
            'subtotal' => 'double',
            'total_with_iva' => 'double',
        ];
    }


    // ─── Relacionamentos ───────────────────────────────────

    public function order(): BelongsTo
    {
        // FIX: era Orders::class — renomeado para Order (singular)
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function invoiceItem(): HasOne
    {
        // FIX: relação em falta no model original
        return $this->hasOne(InvoiceItem::class);
    }

    public function refundItems(): HasMany
    {
        return $this->hasMany(RefundItem::class);
    }
}
