<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubCategory extends Model
{
    use HasFactory;
    

    protected $fillable = ['category_id', 'type_category_id'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function typeCategory()
    {
        return $this->belongsTo(TypeCategory::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
