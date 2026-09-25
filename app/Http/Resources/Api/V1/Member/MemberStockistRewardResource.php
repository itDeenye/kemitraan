<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;

class MemberStockistRewardResource extends ApiResource
{
    public function toArray($request): array
    {
        $isModel = $this->resource instanceof \Illuminate\Database\Eloquent\Model;
        
        $totalTrxAmount = $isModel ? $this->reward_stockist_total_trx_amount : $this->total_spending;
        $bonusValue = $isModel ? $this->reward_stockist_bonus_value : $this->voucher_value;
        $usedValue = $isModel ? $this->reward_stockist_used_value : $this->used_value;
        $expiryDate = $isModel ? $this->reward_stockist_expiry_date : $this->expiry_date;
        
        $percentage = $totalTrxAmount > 0 ? round(($bonusValue / $totalTrxAmount) * 100, 2) : 0;
        
        // Status determination
        $status = 'Aktif';
        if ($usedValue >= $bonusValue && $bonusValue > 0) {
            $status = 'Digunakan';
        } elseif ($expiryDate && \Carbon\Carbon::parse($expiryDate)->isPast()) {
            $status = 'Kedaluwarsa';
        }

        return [
            'id' => $isModel ? $this->reward_stockist_id : $this->id,
            'year' => $isModel ? $this->reward_stockist_year : $this->year,
            'month' => $isModel ? $this->reward_stockist_month : $this->month,
            'total_spending' => $totalTrxAmount,
            'percentage' => (float) $percentage,
            'voucher_value' => $bonusValue,
            'used_value' => $usedValue,
            'remaining_value' => max(0, $bonusValue - $usedValue),
            'status' => $status,
            'expiry_date' => $expiryDate,
            'used_trx_id' => $isModel ? $this->reward_stockist_used_trx_id : $this->used_trx_id,
            'used_trx_code' => $isModel ? $this->usedTransaction?->trx_code : $this->used_trx_code,
            'created_at' => $isModel ? $this->reward_stockist_created_datetime : $this->created_at,
        ];
    }
}
