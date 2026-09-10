<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// FIX: renomeado de Access_level para AccessLevel — convenção Laravel (PascalCase)
// Ficheiro: app/Models/AccessLevel.php
class AccessLevel extends Model
{
    protected $table = 'access_levels';

    protected $fillable = ['name'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
