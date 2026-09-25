<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model audit callback yang diterima dari sistem eksternal.
 */
class IntegrationCallbackLog extends Model
{
    protected $table = 'integration_callback_log';

    protected $primaryKey = 'integration_callback_log_id';

    public $timestamps = false;

    protected $fillable = [
        'integration_callback_log_provider',
        'integration_callback_log_type',
        'integration_callback_log_event',
        'integration_callback_log_headers_json',
        'integration_callback_log_payload_json',
        'integration_callback_log_response_json',
        'integration_callback_log_status',
        'integration_callback_log_http_code',
        'integration_callback_log_processed_datetime',
        'integration_callback_log_created_datetime',
    ];

    protected function casts(): array
    {
        return [
            'integration_callback_log_headers_json' => 'array',
            'integration_callback_log_payload_json' => 'array',
            'integration_callback_log_response_json' => 'array',
            'integration_callback_log_http_code' => 'integer',
            'integration_callback_log_processed_datetime' => 'datetime',
            'integration_callback_log_created_datetime' => 'datetime',
        ];
    }
}
