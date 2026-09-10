<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_photo',
        'phone',
        'active',
        'access_level_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'active'            => 'boolean',
            // FIX: era 'access_level' => 'integer' — campo inexistente.
            // O campo real é access_level_id (FK); o cast correcto fica na relação.
        ];
    }

    // ─── Relacionamentos ───────────────────────────────────

    public function accessLevel(): BelongsTo
    {
        return $this->belongsTo(AccessLevel::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function orders(): HasMany
    {
        // FIX: removido ->with('shift') — causava N+1 em todas as queries de user
        // Carregar o shift apenas quando necessário via eager loading explícito
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    // ─── Helpers de permissão ──────────────────────────────

    // FIX: era $this->access_level === 1 (campo inexistente)
    // Agora compara o access_level_id directamente (1 = Admin pelo seed)
    public function isAdmin(): bool
    {
        return $this->access_level_id === 1;
    }

    public function isSupervisor(): bool
    {
        return $this->access_level_id === 2;
    }

    public function isCashier(): bool
    {
        return $this->access_level_id === 3;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
