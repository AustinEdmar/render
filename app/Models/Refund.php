<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// FIX: renomeado de Refunds para Refund — convenção Laravel (singular)
// Ficheiro: app/Models/Refund.php
class Refund extends Model
{
    protected $fillable = [
        'order_id',
        'payment_id',
        'shift_id',
        'user_id',
        'credit_note_invoice_id',
        'amount',
        'reason',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    // ─── Relacionamentos ───────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        // FIX: era Payments::class — renomeado para Payment (singular)
        return $this->belongsTo(Payment::class);
    }

    public function shift(): BelongsTo
    {
        // FIX: era Shifts::class — renomeado para Shift (singular)
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creditNoteInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'credit_note_invoice_id');
    }

    public function items(): HasMany
    {
        // FIX: RefundItem agora existe (migration + model criados)
        return $this->hasMany(RefundItem::class);
    }
}
