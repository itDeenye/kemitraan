<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model untuk tabel: reward_point_annual_log
 */
class RewardPointAnnualLog extends Model
{
    protected $table = 'reward_point_annual_log';

    protected $primaryKey = 'reward_point_annual_log_id';

    public $timestamps = false;

    protected $fillable = [
        'reward_point_annual_log_member_id',
        'reward_point_annual_log_trx_id',
        'reward_point_annual_log_type',
        'reward_point_annual_log_points',
        'reward_point_annual_log_note',
        'reward_point_annual_log_datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reward_point_annual_log_member_id', 'member_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Trx::class, 'reward_point_annual_log_trx_id', 'trx_id');
    }

    protected function casts(): array
    {
        return [
            'reward_point_annual_log_points' => 'integer',
            'reward_point_annual_log_datetime' => 'datetime',
        ];
    }
}
