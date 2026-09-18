<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeSubmission extends Model
{
    protected $table = 'fe_submissions';

    protected $fillable = [
        'invoice_id',
        'submission_uuid',
        'request_id',
        'endpoint',
        'request_payload',
        'response_payload',
        'fe_status',
        'poll_attempts',
        'last_polled_at',
        'error_list',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'error_list' => 'array',
        'last_polled_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
