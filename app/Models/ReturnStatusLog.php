<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model untuk tabel: return_status_log
 */
class ReturnStatusLog extends Model
{
    protected $table = 'return_status_log';

    protected $primaryKey = 'return_status_log_id';

    public $timestamps = false;

    protected $fillable = [
        'return_status_log_return_id',
        'return_status_log_status',
        'return_status_log_note',
        'return_status_log_created_by',
        'return_status_log_created_datetime',
    ];

    protected function casts(): array
    {
        return ['return_status_log_created_datetime' => 'datetime'];
    }

    public function returnModel(): BelongsTo
    {
        return $this->belongsTo(ReturnModel::class, 'return_status_log_return_id', 'return_id');
    }
}
