<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// FIX: renomeado de Orders para Order — convenção Laravel (singular)
// Ficheiro: app/Models/Order.php
class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shift_id',
        'customer_id',
        'status',
        'subtotal',
        'iva',
        'discount',
        'total',
        'invoice_generated',
        'notes',
        'opened_at',
        'closed_at',
        // FIX: removidos invoice_series e invoice_number — não existem na tabela orders
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'iva' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'invoice_generated' => 'boolean',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    // ─── Relacionamentos ───────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        // FIX: era Shifts::class — renomeado para Shift (singular)
        return $this->belongsTo(Shift::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function payments(): HasMany
    {
        // FIX: era Payments::class — renomeado para Payment (singular)
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        // FIX: era Refunds::class — renomeado para Refund (singular)
        return $this->hasMany(Refund::class);
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
