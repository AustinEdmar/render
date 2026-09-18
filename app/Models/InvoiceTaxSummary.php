<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceTaxSummary extends Model
{
    protected $fillable = [
        'invoice_id',
        'tax_rate_id',
        'tax_code',
        'tax_rate',
        'taxable_amount',
        'tax_amount',
        'tax_exemption_reason',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate'      => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'tax_amount'    => 'decimal:2',
        ];
    }

    // ─── Relacionamentos ───────────────────────────────────

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
