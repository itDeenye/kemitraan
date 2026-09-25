<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Models\RewardPointMonthly;
use Illuminate\Http\Request;

class AdminMonthlyRewardResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $reward = $this->resource instanceof RewardPointMonthly ? $this->resource : null;
        $level = $reward?->memberLevel;

        return [
            'id' => (int) ($reward?->reward_point_monthly_id ?? $this->resource->id),
            'member' => [
                'id' => (int) ($reward?->reward_point_monthly_member_id ?? $this->resource->member_id),
                'code' => $reward?->member?->member_code ?? $this->resource->member_code,
                'name' => $reward?->member?->member_name ?? $this->resource->member_name,
                'level' => [
                    'id' => (int) ($reward?->reward_point_monthly_member_level_id ?? $this->resource->member_level_id),
                    'code' => $level?->member_level_code ?? $this->resource->member_level_code,
                    'name' => $level?->member_level_name ?? $this->resource->member_level_name,
                ],
            ],
            'upline_id' => (int) ($reward?->reward_point_monthly_upline_id ?? $this->resource->upline_id),
            'year' => (int) ($reward?->reward_point_monthly_year ?? $this->resource->year),
            'month' => (int) ($reward?->reward_point_monthly_month ?? $this->resource->month),
            'total_points' => (int) ($reward?->reward_point_monthly_total_qty ?? $this->resource->total_points),
            'point_value' => (int) ($level?->member_level_point_value ?? $this->resource->point_value),
            'reward_value' => (int) ($reward?->reward_point_monthly_bonus_value ?? $this->resource->reward_value),
            'is_processed' => (bool) ($reward?->reward_point_monthly_is_processed ?? $this->resource->is_processed),
            'administrator_id' => (int) ($reward?->reward_point_monthly_admin_id ?? $this->resource->administrator_id),
            'processed_at' => $reward
                ? $reward->reward_point_monthly_processed_datetime?->toAtomString()
                : $this->resource->processed_at,
        ];
    }
}
