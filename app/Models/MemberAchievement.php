<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberAchievement extends Model
{
    protected $table = 'member_achievement';

    protected $primaryKey = 'member_achievement_id';

    public $timestamps = false;

    protected $fillable = [
        'member_achievement_member_id',
        'member_achievement_year',
        'member_achievement_month',
        'member_achievement_point',
        'member_achievement_customer_count',
        'member_achievement_total_trx_amount',
        'member_achievement_create_datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_achievement_member_id', 'member_id');
    }

    protected function casts(): array
    {
        return [
            'member_achievement_year' => 'integer',
            'member_achievement_month' => 'integer',
            'member_achievement_point' => 'integer',
            'member_achievement_customer_count' => 'integer',
            'member_achievement_total_trx_amount' => 'integer',
            'member_achievement_create_datetime' => 'datetime',
        ];
    }
}
