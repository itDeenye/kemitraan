<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;
use App\Models\RewardPointMonthly;
use Illuminate\Http\Request;

class MemberDownlineMonthlyRewardResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $reward = $this->resource instanceof RewardPointMonthly ? $this->resource : null;
        $bankAccount = $reward?->member?->defaultBankAccount;
        $bankId = $bankAccount?->member_bank_account_bank_id ?? $this->resource->bank_id ?? null;

        return [
            'id' => (int) ($reward?->reward_point_monthly_id ?? $this->resource->id),
            'member' => [
                'id' => (int) ($reward?->reward_point_monthly_member_id ?? $this->resource->member_id),
                'code' => $reward?->member?->member_code ?? $this->resource->member_code,
                'name' => $reward?->member?->member_name ?? $this->resource->member_name,
                'level' => [
                    'id' => (int) ($reward?->reward_point_monthly_member_level_id ?? $this->resource->member_level_id),
                    'code' => $reward?->memberLevel?->member_level_code ?? $this->resource->member_level_code,
                    'name' => $reward?->memberLevel?->member_level_name ?? $this->resource->member_level_name,
                ],
            ],
            'bank' => $bankId ? [
                'id' => (int) $bankId,
                'code' => $bankAccount?->bank?->bank_code ?? $this->resource->bank_code,
                'name' => $bankAccount?->bank?->bank_name ?? $this->resource->bank_name,
                'account_name' => $bankAccount?->member_bank_account_name ?? $this->resource->bank_account_name,
                'account_number' => $bankAccount?->member_bank_account_number ?? $this->resource->bank_account_number,
            ] : null,
            'year' => (int) ($reward?->reward_point_monthly_year ?? $this->resource->year),
            'month' => (int) ($reward?->reward_point_monthly_month ?? $this->resource->month),
            'total_points' => (int) ($reward?->reward_point_monthly_total_qty ?? $this->resource->total_points),
            'point_value' => (int) ($reward?->memberLevel?->member_level_point_value ?? $this->resource->point_value),
            'reward_value' => (int) ($reward?->reward_point_monthly_bonus_value ?? $this->resource->reward_value),
            'is_processed' => (bool) ($reward?->reward_point_monthly_is_processed ?? $this->resource->is_processed),
            'processed_at' => $reward?->reward_point_monthly_processed_datetime ?? $this->resource->processed_at,
        ];
    }
}
