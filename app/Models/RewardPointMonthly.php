<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model untuk tabel: reward_point_monthly
 */
class RewardPointMonthly extends Model
{
    protected $table = 'reward_point_monthly';

    protected $primaryKey = 'reward_point_monthly_id';

    public $timestamps = false;

    protected $fillable = [
        'reward_point_monthly_upline_id',
        'reward_point_monthly_upline_level_id',
        'reward_point_monthly_member_id',
        'reward_point_monthly_member_level_id',
        'reward_point_monthly_year',
        'reward_point_monthly_month',
        'reward_point_monthly_total_qty',
        'reward_point_monthly_bonus_value',
        'reward_point_monthly_admin_id',
        'reward_point_monthly_is_processed',
        'reward_point_monthly_processed_datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reward_point_monthly_member_id', 'member_id');
    }

    public function memberLevel(): BelongsTo
    {
        return $this->belongsTo(MemberLevel::class, 'reward_point_monthly_member_level_id', 'member_level_id');
    }

    public function upline(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reward_point_monthly_upline_id', 'member_id');
    }

    protected function casts(): array
    {
        return [
            'reward_point_monthly_year' => 'integer',
            'reward_point_monthly_month' => 'integer',
            'reward_point_monthly_total_qty' => 'integer',
            'reward_point_monthly_bonus_value' => 'integer',
            'reward_point_monthly_is_processed' => 'boolean',
            'reward_point_monthly_processed_datetime' => 'datetime',
        ];
    }
}
