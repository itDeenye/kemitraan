<?php

namespace App\Services\Reward;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;

class MemberRewardEligibility
{
    /** @return array{sharing_profit: bool, stockist_voucher: bool} */
    public function access(Member $member): array
    {
        $voucherAllowed = $this->canAccessStockistVoucher($member);

        return [
            'sharing_profit' => $voucherAllowed && $this->hasDistributorDownline($member),
            'stockist_voucher' => $voucherAllowed,
        ];
    }

    public function canAccessSharingProfit(Member $member): bool
    {
        return $this->canAccessStockistVoucher($member) && $this->hasDistributorDownline($member);
    }

    public function canAccessStockistVoucher(Member $member): bool
    {
        $member->loadMissing(['level', 'stockist']);

        return (int) $member->member_status === 1
            && $member->level?->member_level_code === 'DST'
            && $member->level->member_level_is_active
            && $member->stockist?->stockist_is_active
            && ! $member->stockist->stockist_is_deleted;
    }

    private function hasDistributorDownline(Member $member): bool
    {
        return $member->downlines()
            ->where('member_status', 1)
            ->whereHas('level', fn (Builder $query): Builder => $query
                ->where('member_level_code', 'DST')
                ->where('member_level_is_active', 1))
            ->exists();
    }
}
