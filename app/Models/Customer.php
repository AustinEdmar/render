<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'tax_number',
        'customer_type',
        'address',
        'city',
        'province',
        'postal_code',
        'country',
        'is_final_consumer',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_final_consumer' => 'boolean',
            'is_active'         => 'boolean',
        ];
    }

    // ─── Relacionamentos ───────────────────────────────────

    public function orders(): HasMany
    {
        // FIX: era Orders::class — o model correcto é Order (singular, renomeado)
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // ─── Helpers ───────────────────────────────────────────

    public function isFinalConsumer(): bool
    {
        return (bool) $this->is_final_consumer;
    }
}
