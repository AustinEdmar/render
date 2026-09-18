<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// FIX: ficheiro original não tinha namespace, nem closing brace, nem HasMany tipado
class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image_path',
    ];

    // ─── Relacionamentos ───────────────────────────────────

    public function products(): HasMany
    {
        // FIX: era Products() com P maiúsculo — convenção Laravel é camelCase minúsculo
        return $this->hasMany(Product::class);
    }
}
