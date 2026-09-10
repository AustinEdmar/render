<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'product_code',
        'description',
        'price',
        'unit',
        'tax_rate_id',
        'tax_exemption_reason',
        'stock',
        'barcode',
        'category_id',
        'image_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price'    => 'decimal:2',
            'stock'    => 'integer',
            'is_active' => 'boolean',
            // FIX: removido 'iva' => 'integer' — campo não existe na tabela
        ];
    }

    // ─── Relacionamentos ───────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query, int $threshold = 5)
    {
        return $query->where('stock', '<=', $threshold);
    }

    // ─── Helpers ───────────────────────────────────────────

    // Retorna a percentagem de IVA via relação (substitui o campo 'iva' removido)
    public function getTaxPercentageAttribute(): float
    {
        return (float) ($this->taxRate?->tax_percentage ?? 0);
    }

    // Retorna o tax_code via relação (NOR, RED, ISE, EXC, OUT)
    public function getTaxCodeAttribute(): string
    {
        return $this->taxRate?->tax_code ?? 'ISE';
    }
}
