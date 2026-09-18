<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

// FIX: renomeado de Stockmovement para StockMovement — convenção PascalCase
// Ficheiro: app/Models/StockMovement.php
class StockMovement extends Model
{
    protected $table = 'stock_movements';

    protected $fillable = [
        'product_id',
        'user_id',
        'type',
        'quantity',
        'stock_before',
        'stock_after',
        // FIX: adicionados campos do morphTo — agora existem na migration (nullableMorphs)
        'reference_type',
        'reference_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity'    => 'integer',
            'stock_before' => 'integer',
            'stock_after'  => 'integer',
        ];
    }

    // ─── Relacionamentos ───────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Referência polimórfica: aponta para OrderItem ou RefundItem como origem
    // FIX: morphTo() agora tem os campos correspondentes na migration
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeEntradas($query)
    {
        return $query->where('quantity', '>', 0);
    }

    public function scopeSaidas($query)
    {
        return $query->where('quantity', '<', 0);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
