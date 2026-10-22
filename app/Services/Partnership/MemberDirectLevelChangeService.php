<?php

namespace App\Services\Partnership;

use App\Exceptions\ProcessException;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberHistory;
use App\Models\MemberRegistration;
use App\Models\SiteAdministrator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MemberDirectLevelChangeService
{
    public function __construct(private readonly MemberPlacementService $placementService) {}

    public function eligibleQuery(): Builder
    {
        return Member::query()->where('member_status', 1)
            ->whereDoesntHave('downlines')->whereDoesntHave('purchases')->whereDoesntHave('sales')
            ->whereDoesntHave('stockist')
            ->whereDoesntHave('networkTransfers', fn ($query) => $query->whereIn('network_switch_status', ['scheduled', 'approved'])
                ->whereNull('network_switch_applied_datetime'))
            ->whereNotIn('member_id', MemberRegistration::query()->select('member_registration_upline_member_id')
                ->where('member_registration_status', 'requested'));
    }

    public function options(Member $member): array
    {
        $allowed = $this->eligibleQuery()->whereKey($member->getKey())->exists();

        return ['can_change_level' => $allowed,
            'message' => $allowed ? 'Mitra memenuhi syarat untuk perubahan level.'
                : 'Ubah level hanya untuk mitra aktif tanpa downline, riwayat transaksi, pengajuan jaringan, atau status stokis.',
            ...($allowed ? $this->placementService->options((int) $member->getKey()) : ['levels' => [], 'uplines' => []]),
        ];
    }

    public function change(Member $member, SiteAdministrator $administrator, array $data): Member
    {
        return DB::transaction(function () use ($member, $administrator, $data): Member {
            $locked = Member::query()->with('level')->whereKey($member->getKey())->lockForUpdate()->firstOrFail();
            if (! $this->eligibleQuery()->whereKey($locked->getKey())->exists()) {
                throw new ProcessException('Level tidak dapat diubah: mitra harus aktif, belum punya downline, belum pernah bertransaksi, dan tidak sedang dalam proses perubahan jaringan atau menjadi stokis.');
            }
            [$level, $upline] = $this->placementService->resolve((int) $data['level_id'], (int) ($data['upline_member_id'] ?? 0), (int) $locked->getKey());
            if ((int) $locked->member_member_level_id === (int) $level->getKey()) {
                throw new ProcessException('Pilih level tujuan yang berbeda dari level saat ini.');
            }
            $group = MemberGroup::query()->where('member_group_name', $level->member_level_name)->where('member_group_is_active', 1)->first();
            if ($group === null) {
                throw new ProcessException('Grup akses level tujuan belum tersedia atau tidak aktif.');
            }
            $fromLevel = $locked->member_member_level_id;
            $locked->update(['member_member_level_id' => $level->getKey(), 'member_parent_member_id' => $upline?->getKey() ?? 0]);
            $locked->accounts()->update(['member_account_member_group_id' => $group->getKey()]);
            MemberHistory::query()->create([
                'member_history_member_id' => $locked->getKey(), 'member_history_action' => 'level_correction',
                'member_history_from_level_id' => $fromLevel, 'member_history_to_level_id' => $level->getKey(),
                'member_history_upline_member_id' => $upline?->getKey() ?? 0,
                'member_history_upline_member_level_id' => $upline?->member_member_level_id ?? 0,
                'member_history_downline_member_id' => 0,
                'member_history_reason' => filled($data['note'] ?? null) ? $data['note'] : 'Perubahan level mitra oleh admin.',
                'member_history_approved_by' => $administrator->getKey(), 'member_history_datetime' => now(),
            ]);

            return $locked->refresh();
        }, 3);
    }
}
