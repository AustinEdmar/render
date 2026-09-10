<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values'   => 'array',
            'new_values'   => 'array',
            'performed_at' => 'datetime',
        ];
    }

    // ─── Relacionamentos ───────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Factory helper ────────────────────────────────────

    // Regista uma entrada de auditoria de forma centralizada
    public static function record(
        string $action,
        Model $model,
        array $oldValues = [],
        array $newValues = [],
        ?int $userId = null
    ): self {
        return self::create([
            'user_id'      => $userId ?? auth()->id(),
            'action'       => $action,
            'model_type'   => class_basename($model),
            'model_id'     => $model->getKey(),
            'old_values'   => $oldValues ?: null,
            'new_values'   => $newValues ?: null,
            'ip_address'   => request()?->ip(),
            'user_agent'   => request()?->userAgent(),
            'performed_at' => now(),
        ]);
    }
}
