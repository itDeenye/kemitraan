<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;
use Illuminate\Database\Eloquent\Model;

class MemberMonthlyRewardResource extends ApiResource
{
    public function toArray($request): array
    {
        $isModel = $this->resource instanceof Model;
        $responsibleSponsorId = (int) ($isModel
            ? $this->reward_point_monthly_upline_id
            : $this->responsible_sponsor_id);
        $responsibleSponsor = $responsibleSponsorId > 0 ? [
            'id' => $responsibleSponsorId,
            'code' => $isModel ? $this->upline?->member_code : $this->responsible_sponsor_code,
            'name' => $isModel ? $this->upline?->member_name : $this->responsible_sponsor_name,
            'level' => [
                'id' => (int) ($isModel
                    ? $this->upline?->member_member_level_id
                    : $this->responsible_sponsor_level_id),
                'code' => $isModel
                    ? $this->upline?->level?->member_level_code
                    : $this->responsible_sponsor_level_code,
                'name' => $isModel
                    ? $this->upline?->level?->member_level_name
                    : $this->responsible_sponsor_level_name,
            ],
        ] : null;

        return [
            'id' => $isModel ? $this->reward_point_monthly_id : $this->id,
            'year' => $isModel ? $this->reward_point_monthly_year : $this->year,
            'month' => $isModel ? $this->reward_point_monthly_month : $this->month,
            'total_points' => $isModel ? $this->reward_point_monthly_total_qty : $this->total_points,
            'reward_value' => $isModel ? $this->reward_point_monthly_bonus_value : $this->reward_value,
            'is_processed' => (bool) ($isModel ? $this->reward_point_monthly_is_processed : $this->is_processed),
            'processed_at' => $isModel ? $this->reward_point_monthly_processed_datetime : $this->processed_at,
            'payment_responsibility' => [
                'type' => $responsibleSponsor ? 'sponsor' : 'company',
                'label' => $responsibleSponsor
                    ? "{$responsibleSponsor['code']} - {$responsibleSponsor['name']}"
                    : 'Perusahaan DNY Skincare',
                'responsible_sponsor' => $responsibleSponsor,
            ],
        ];
    }
}
