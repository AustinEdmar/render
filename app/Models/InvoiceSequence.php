<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceSequence extends Model
{
    protected $fillable = [
        'document_type',
        'series',
        'year',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'year'        => 'integer',
            'last_number' => 'integer',
        ];
    }
}
