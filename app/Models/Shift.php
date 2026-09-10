<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// FIX: renomeado de Shifts para Shift — convenção Laravel (singular)
// Ficheiro: app/Models/Shift.php
class Shift extends Model
{
    protected $fillable = [
        'user_id',
        'initial_amount',
        'expected_cash_amount',
        'difference',
        'gross_sales',
        'refund_total',
        'net_sales',
        'final_cash_amount',
        'status',
        'terminal_id',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'initial_amount'       => 'decimal:2',
            'expected_cash_amount' => 'decimal:2',
            'difference'           => 'decimal:2',
            'gross_sales'          => 'decimal:2',
            'refund_total'         => 'decimal:2',
            'net_sales'            => 'decimal:2',
            'final_cash_amount'    => 'decimal:2',
            'opened_at'            => 'datetime',
            'closed_at'            => 'datetime',
        ];
    }

    // ─── Relacionamentos ───────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }
}
