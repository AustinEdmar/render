<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $table = 'company';

    protected $fillable = [
        'name',
        'trade_name',
        'nif',
        'cae',
        'address',
        'city',
        'province',
        'postal_code',
        'country',
        'phone',
        'email',
        'website',
        'logo_path',
        'software_name',
        'certificate_number',
        'certificate_issuer',
        'software_version',
        'currency',
        'vat_regime',
    ];

    protected function casts(): array
    {
        return [
            'vat_regime' => 'string',
        ];
    }
}
