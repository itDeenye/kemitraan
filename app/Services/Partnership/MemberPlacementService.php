<?php

namespace App\Services\Partnership;

use App\Exceptions\ProcessException;
use App\Models\Member;
use App\Models\MemberLevel;
use App\Models\MemberNetworkSwitch;

class MemberPlacementService
{
    public function options(?int $excludeMemberId = null): array
    {
        return [
            'levels' => MemberLevel::query()->whereIn('member_level_code', ['DST', 'AGT', 'RSL'])
                ->where('member_level_is_active', 1)->orderBy('member_level_sort_order')
                ->get()->map(fn (MemberLevel $level): array => [
                    'id' => (int) $level->getKey(), 'code' => $level->member_level_code, 'name' => $level->member_level_name,
                ])->all(),
            'uplines' => Member::query()->with('level')->where('member_status', 1)
                ->when($excludeMemberId !== null, fn ($query) => $query->whereKeyNot($excludeMemberId))
                ->whereHas('level', fn ($query) => $query->whereIn('member_level_code', ['DST', 'AGT'])->where('member_level_is_active', 1))
                ->whereNotIn('member_id', MemberNetworkSwitch::query()->select('network_switch_member_id')
                    ->where('network_switch_type', 'downgrade')->whereIn('network_switch_status', ['scheduled', 'approved'])
                    ->whereNull('network_switch_applied_datetime'))
                ->orderBy('member_name')->get()->map(fn (Member $member): array => [
                    'id' => (int) $member->getKey(), 'code' => $member->member_code, 'name' => $member->member_name,
                    'label' => $member->member_code.' - '.$member->member_name.' ('.$member->level->member_level_name.')',
                    'level_code' => $member->level->member_level_code, 'level_name' => $member->level->member_level_name,
                ])->all(),
        ];
    }

    /** @return array{MemberLevel, ?Member} */
    public function resolve(int $levelId, ?int $uplineId, ?int $excludeMemberId = null): array
    {
        $level = MemberLevel::query()->whereKey($levelId)->where('member_level_is_active', 1)
            ->whereIn('member_level_code', ['DST', 'AGT', 'RSL'])->first();
        if ($level === null) {
            throw new ProcessException('Level tujuan tidak aktif atau tidak tersedia.');
        }
        if ($level->member_level_code === 'DST') {
            if ($uplineId) {
                throw new ProcessException('Distributor tidak boleh memiliki upline.');
            }

            return [$level, null];
        }
        $upline = Member::query()->with('level')->whereKey($uplineId)->where('member_status', 1)->lockForUpdate()->first();
        $allowed = $level->member_level_code === 'AGT' ? ['DST'] : ['DST', 'AGT'];
        if ($upline === null || (int) $upline->getKey() === $excludeMemberId
            || ! $upline->level?->member_level_is_active || ! in_array($upline->level->member_level_code, $allowed, true)) {
            throw new ProcessException('Upline wajib aktif dan sesuai dengan level tujuan.');
        }
        if (MemberNetworkSwitch::query()->where('network_switch_member_id', $upline->getKey())
            ->where('network_switch_type', 'downgrade')->whereIn('network_switch_status', ['scheduled', 'approved'])
            ->whereNull('network_switch_applied_datetime')->exists()) {
            throw new ProcessException('Upline sedang dijadwalkan turun tingkat dan tidak dapat menerima mitra baru.');
        }

        return [$level, $upline];
    }
}
