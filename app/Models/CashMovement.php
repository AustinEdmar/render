<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// FIX: renomeado de Cashmovement para CashMovement — convenção PascalCase
// Ficheiro: app/Models/CashMovement.php
class CashMovement extends Model
{
    protected $table = 'cash_movements';

    protected $fillable = [
        'shift_id',
        'user_id',
        'type',
        'amount',
        // FIX: currency estava em falta no $fillable original mas existe na migration e no controller
        'currency',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    // ─── Relacionamentos ───────────────────────────────────

    public function shift(): BelongsTo
    {
        // FIX: era Shifts::class — renomeado para Shift (singular)
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeInflows($query)
    {
        return $query->where('type', 'inflow');
    }

    public function scopeOutflows($query)
    {
        return $query->where('type', 'outflow');
    }
}
