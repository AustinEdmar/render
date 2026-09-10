<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxRate extends Model
{
    protected $fillable = [
        'tax_type',
        'tax_code',
        'description',
        'tax_percentage',
        'country',
        'is_active',
        'exemption_reason',
    ];

    protected function casts(): array
    {
        return [
            'tax_percentage' => 'decimal:2',
            'is_active'      => 'boolean',
        ];
    }

    // ─── Relacionamentos ───────────────────────────────────

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function invoiceTaxSummaries(): HasMany
    {
        return $this->hasMany(InvoiceTaxSummary::class);
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ─── Helpers ───────────────────────────────────────────

    public function isZeroRated(): bool
    {
        return (float) $this->tax_percentage === 0.0;
    }
}
