<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Models\RewardStockist;
use Illuminate\Http\Request;

class AdminStockistRewardResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $reward = $this->resource instanceof RewardStockist ? $this->resource : null;
        $bonusValue = (int) ($reward?->reward_stockist_bonus_value ?? $this->resource->voucher_value);
        $usedValue = (int) ($reward?->reward_stockist_used_value ?? $this->resource->used_value);

        return [
            'id' => (int) ($reward?->reward_stockist_id ?? $this->resource->id),
            'member' => [
                'id' => (int) ($reward?->reward_stockist_member_id ?? $this->resource->member_id),
                'code' => $reward?->member?->member_code ?? $this->resource->member_code,
                'name' => $reward?->member?->member_name ?? $this->resource->member_name,
            ],
            'year' => (int) ($reward?->reward_stockist_year ?? $this->resource->year),
            'month' => (int) ($reward?->reward_stockist_month ?? $this->resource->month),
            'total_spending' => (int) ($reward?->reward_stockist_total_trx_amount ?? $this->resource->total_spending),
            'voucher_value' => $bonusValue,
            'used_value' => $usedValue,
            'remaining_value' => max(0, $bonusValue - $usedValue),
            'used_transaction' => [
                'id' => (int) ($reward?->reward_stockist_used_trx_id ?? $this->resource->used_trx_id),
                'code' => $reward?->usedTransaction?->trx_code ?? $this->resource->used_trx_code,
            ],
            'expiry_date' => $reward
                ? $reward->reward_stockist_expiry_date?->toDateString()
                : $this->resource->expiry_date,
            'status' => $usedValue >= $bonusValue && $bonusValue > 0 ? 'used' : 'available',
        ];
    }
}
