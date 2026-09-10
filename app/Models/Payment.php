<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// FIX: renomeado de Payments para Payment — convenção Laravel (singular)
// Ficheiro: app/Models/Payment.php
class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'shift_id',
        'user_id',
        // FIX: user_id adicionado — existia no model original mas faltava na migration (já corrigida)
        'received',
        'change',
        'status',
        'method_payment',
        'amount',
        'currency',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'received' => 'decimal:2',
            'change'   => 'decimal:2',
            'amount'   => 'decimal:2',
            'paid_at'  => 'datetime',
        ];
    }

    // ─── Relacionamentos ───────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function refunds(): HasMany
    {
        // FIX: era Refunds::class — renomeado para Refund (singular)
        return $this->hasMany(Refund::class);
    }
}
