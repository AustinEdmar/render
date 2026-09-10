<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeSeries extends Model
{
    protected $table = 'fe_series';

    protected $fillable = [
        'document_type',
        'series_year',
        'establishment_number',
        'contingency_indicator',
        'series_code',
        'authorized_quantity',
        'first_document_no',
        'last_document_no',
        'used_count',
        'status',
        'request_submission_uuid',
        'request_payload',
        'response_payload',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];
}
