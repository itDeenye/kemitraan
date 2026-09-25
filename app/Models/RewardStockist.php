<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardStockist extends Model
{
    protected $table = 'reward_stockist';

    protected $primaryKey = 'reward_stockist_id';

    public $timestamps = false;

    protected $guarded = [];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reward_stockist_member_id', 'member_id');
    }

    public function usedTransaction(): BelongsTo
    {
        return $this->belongsTo(Trx::class, 'reward_stockist_used_trx_id', 'trx_id');
    }

    protected function casts(): array
    {
        return [
            'reward_stockist_year' => 'integer',
            'reward_stockist_month' => 'integer',
            'reward_stockist_total_trx_amount' => 'integer',
            'reward_stockist_bonus_value' => 'integer',
            'reward_stockist_used_value' => 'integer',
            'reward_stockist_expiry_date' => 'date',
            'reward_stockist_created_datetime' => 'datetime',
        ];
    }
}
