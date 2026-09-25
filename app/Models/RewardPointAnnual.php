<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model untuk tabel: reward_point_annual
 */
class RewardPointAnnual extends Model
{
    protected $table = 'reward_point_annual';

    protected $primaryKey = 'reward_point_annual_id';

    public $timestamps = false;

    protected $fillable = [
        'reward_point_annual_member_id',
        'reward_point_annual_year',
        'reward_point_annual_total_points',
        'reward_point_annual_last_updated_datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reward_point_annual_member_id', 'member_id');
    }

    protected function casts(): array
    {
        return [
            'reward_point_annual_year' => 'integer',
            'reward_point_annual_total_points' => 'integer',
            'reward_point_annual_last_updated_datetime' => 'datetime',
        ];
    }
}
