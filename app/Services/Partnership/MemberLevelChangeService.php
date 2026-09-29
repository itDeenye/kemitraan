<?php

namespace App\Services\Partnership;

use App\Exceptions\ProcessException;
use App\Libraries\DataTable;
use App\Models\Member;
use App\Models\MemberAchievement;
use App\Models\MemberHistory;
use App\Models\MemberLevel;
use App\Models\MemberNetworkSwitch;
use App\Models\MemberUpgradeQualified;
use App\Models\SiteAdministrator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MemberLevelChangeService
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function upgrades(array $params): array
    {
        return DataTable::select([
            'member_upgrade_qualified.member_upgrade_qualified_id as id',
            'member.member_id as member_id',
            'member.member_code as member_code',
            'member.member_name as member_name',
            'from_level.member_level_id as from_level_id',
            'from_level.member_level_code as from_level_code',
            'from_level.member_level_name as from_level_name',
            'to_level.member_level_id as to_level_id',
            'to_level.member_level_code as to_level_code',
            'to_level.member_level_name as to_level_name',
            'member_upgrade_qualified.member_upgrade_qualified_from_year_month as from_period',
            'member_upgrade_qualified.member_upgrade_qualified_to_year_month as to_period',
            'member_upgrade_qualified.member_upgrade_qualified_status as status',
            'site_administrator.administrator_id as administrator_id',
            'site_administrator.administrator_name as administrator_name',
            'member_upgrade_qualified.member_upgrade_qualified_effective_date as effective_date',
            'member_upgrade_qualified.member_upgrade_qualified_created_datetime as created_at',
        ])
            ->from('member_upgrade_qualified')
            ->leftJoin('member', 'member.member_id = member_upgrade_qualified.member_upgrade_qualified_member_id')
            ->leftJoin('member_level as from_level', 'from_level.member_level_id = member_upgrade_qualified.member_upgrade_qualified_from_level_id')
            ->leftJoin('member_level as to_level', 'to_level.member_level_id = member_upgrade_qualified.member_upgrade_qualified_to_level_id')
            ->leftJoin('site_administrator', 'site_administrator.administrator_id = member_upgrade_qualified.member_upgrade_qualified_admin_id')
            ->search(['member_code', 'member_name', 'from_level_name', 'to_level_name'])
            ->defaultSort('-id')
            ->get($params);
    }

    public function upgrade(MemberUpgradeQualified $upgrade): MemberUpgradeQualified
    {
        return $this->upgradeDetailQuery()->findOrFail($upgrade->getKey());
    }

    public function approveUpgrade(
        MemberUpgradeQualified $upgrade,
        SiteAdministrator $administrator,
        ?string $note
    ): MemberUpgradeQualified {
        return DB::transaction(function () use ($upgrade, $administrator, $note): MemberUpgradeQualified {
            $lockedUpgrade = MemberUpgradeQualified::query()
                ->whereKey($upgrade->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->ensureUpgradeCanBeProcessed($lockedUpgrade);

            $member = Member::query()
                ->with(['level', 'parent.level', 'parent.parent.level'])
                ->whereKey($lockedUpgrade->member_upgrade_qualified_member_id)
                ->where('member_status', 1)
                ->lockForUpdate()
                ->first();
            $toLevel = MemberLevel::query()
                ->whereKey($lockedUpgrade->member_upgrade_qualified_to_level_id)
                ->where('member_level_is_active', 1)
                ->first();

            if (! $member || ! $member->level || ! $toLevel) {
                throw new ProcessException('Mitra atau tingkat tujuan tidak aktif dan tidak dapat diproses.');
            }

            if ((int) $member->member_member_level_id !== (int) $lockedUpgrade->member_upgrade_qualified_from_level_id) {
                throw new ProcessException('Tingkat mitra sudah berubah dari data kelayakan kenaikan tingkat.');
            }

            $this->ensureValidUpgradeTransition($member->level, $toLevel);
            $this->ensureNoPendingTransfer($member);
            $effectiveDate = CarbonImmutable::now()->addMonth()->startOfMonth();
            $effectiveDateString = $effectiveDate->toDateString();
            $toParentId = $this->upgradeParentId($member, $toLevel);
            $now = now();

            $lockedUpgrade->update([
                'member_upgrade_qualified_status' => 'scheduled',
                'member_upgrade_qualified_admin_id' => $administrator->getKey(),
                'member_upgrade_qualified_approved_datetime' => $now,
                'member_upgrade_qualified_effective_date' => $effectiveDateString,
                'member_upgrade_qualified_last_update_datetime' => $now,
            ]);

            MemberNetworkSwitch::query()->create([
                'network_switch_upgrade_qualified_id' => $lockedUpgrade->getKey(),
                'network_switch_member_id' => $member->getKey(),
                'network_switch_from_parent_member_id' => $member->member_parent_member_id,
                'network_switch_to_parent_member_id' => $toParentId,
                'network_switch_from_level_id' => $member->member_member_level_id,
                'network_switch_to_level_id' => $toLevel->getKey(),
                'network_switch_type' => 'upgrade',
                'network_switch_status' => 'approved',
                'network_switch_admin_id' => $administrator->getKey(),
                'network_switch_approved_datetime' => $now,
                'network_switch_effective_date' => $effectiveDateString,
                'network_switch_transfer_note' => $note ?: 'Perpindahan jaringan karena upgrade level.',
                'network_switch_created_datetime' => $now,
            ]);

            return $this->upgrade($lockedUpgrade);
        });
    }

    public function rejectUpgrade(
        MemberUpgradeQualified $upgrade,
        SiteAdministrator $administrator
    ): MemberUpgradeQualified {
        return DB::transaction(function () use ($upgrade, $administrator): MemberUpgradeQualified {
            $lockedUpgrade = MemberUpgradeQualified::query()
                ->whereKey($upgrade->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->ensureUpgradeCanBeProcessed($lockedUpgrade);
            $lockedUpgrade->update([
                'member_upgrade_qualified_status' => 'rejected',
                'member_upgrade_qualified_admin_id' => $administrator->getKey(),
                'member_upgrade_qualified_last_update_datetime' => now(),
            ]);

            return $this->upgrade($lockedUpgrade);
        });
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function downgrades(array $params): array
    {
        return DataTable::select([
            'member_network_switch.network_switch_transfer_id as id',
            'member.member_id as member_id',
            'member.member_code as member_code',
            'member.member_name as member_name',
            'from_parent.member_code as from_parent_code',
            'from_parent.member_name as from_parent_name',
            'to_parent.member_code as to_parent_code',
            'to_parent.member_name as to_parent_name',
            'from_level.member_level_id as from_level_id',
            'from_level.member_level_code as from_level_code',
            'from_level.member_level_name as from_level_name',
            'to_level.member_level_id as to_level_id',
            'to_level.member_level_code as to_level_code',
            'to_level.member_level_name as to_level_name',
            'member_network_switch.network_switch_status as status',
            'site_administrator.administrator_id as administrator_id',
            'site_administrator.administrator_name as administrator_name',
            'member_network_switch.network_switch_effective_date as effective_date',
            'member_network_switch.network_switch_created_datetime as created_at',
        ])
            ->from('member_network_switch')
            ->leftJoin('member', 'member.member_id = member_network_switch.network_switch_member_id')
            ->leftJoin('member as from_parent', 'from_parent.member_id = member_network_switch.network_switch_from_parent_member_id')
            ->leftJoin('member as to_parent', 'to_parent.member_id = member_network_switch.network_switch_to_parent_member_id')
            ->leftJoin('member_level as from_level', 'from_level.member_level_id = member_network_switch.network_switch_from_level_id')
            ->leftJoin('member_level as to_level', 'to_level.member_level_id = member_network_switch.network_switch_to_level_id')
            ->leftJoin('site_administrator', 'site_administrator.administrator_id = member_network_switch.network_switch_admin_id')
            ->where('member_network_switch.network_switch_type', 'downgrade')
            ->search(['member_code', 'member_name', 'from_parent_name', 'to_parent_name'])
            ->defaultSort('-id')
            ->get($params);
    }

    public function downgrade(MemberNetworkSwitch $downgrade): MemberNetworkSwitch
    {
        return $this->downgradeDetailQuery()
            ->where('network_switch_type', 'downgrade')
            ->findOrFail($downgrade->getKey());
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function downgradeSponsorOptions(array $params): array
    {
        $member = Member::query()
            ->with('level')
            ->whereKey($params['member_id'])
            ->where('member_status', 1)
            ->firstOrFail();
        $toLevel = MemberLevel::query()
            ->whereKey($params['to_level_id'])
            ->where('member_level_is_active', 1)
            ->firstOrFail();

        if (! $member->level) {
            throw new ProcessException('Tingkat mitra saat ini tidak tersedia.');
        }
        $this->ensureValidDowngradeTransition($member->level, $toLevel);

        $expectedParentCode = $this->expectedParentCode($toLevel);
        $this->ensureNoPendingTransfer($member);
        $excludedMemberIds = [$member->getKey(), ...$this->descendantIds($member)];
        $search = trim((string) ($params['search'] ?? ''));

        $sponsors = Member::query()
            ->select([
                'member_id',
                'member_code',
                'member_name',
                'member_member_level_id',
                'member_parent_member_id',
            ])
            ->with('level:member_level_id,member_level_code,member_level_name')
            ->where('member_status', 1)
            ->whereNotIn('member_id', $excludedMemberIds)
            ->whereHas('level', fn (Builder $query): Builder => $query
                ->where('member_level_code', $expectedParentCode)
                ->where('member_level_is_active', 1))
            ->whereNotIn('member_id', MemberNetworkSwitch::query()
                ->select('network_switch_member_id')
                ->whereIn('network_switch_status', ['scheduled', 'approved'])
                ->whereNull('network_switch_applied_datetime'))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('member_code', 'like', "%{$search}%")
                        ->orWhere('member_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('member_name')
            ->orderBy('member_id')
            ->limit((int) ($params['limit'] ?? 20))
            ->get()
            ->map(fn (Member $sponsor): array => [
                'id' => (int) $sponsor->getKey(),
                'code' => $sponsor->member_code,
                'name' => $sponsor->member_name,
                'label' => "{$sponsor->member_code} - {$sponsor->member_name}",
                'level' => [
                    'id' => (int) $sponsor->member_member_level_id,
                    'code' => $sponsor->level?->member_level_code,
                    'name' => $sponsor->level?->member_level_name,
                ],
            ])
            ->values()
            ->all();

        return [
            'member' => [
                'id' => (int) $member->getKey(),
                'code' => $member->member_code,
                'name' => $member->member_name,
                'level' => [
                    'id' => (int) $member->member_member_level_id,
                    'code' => $member->level?->member_level_code,
                    'name' => $member->level?->member_level_name,
                ],
            ],
            'to_level' => [
                'id' => (int) $toLevel->getKey(),
                'code' => $toLevel->member_level_code,
                'name' => $toLevel->member_level_name,
            ],
            'required_sponsor_level' => $this->levelLabel($expectedParentCode),
            'results' => $sponsors,
        ];
    }

    /** @param array<string, mixed> $data */
    public function scheduleDowngrade(
        Member $member,
        SiteAdministrator $administrator,
        array $data
    ): MemberNetworkSwitch {
        return DB::transaction(function () use ($member, $administrator, $data): MemberNetworkSwitch {
            $lockedMember = Member::query()
                ->with('level')
                ->whereKey($member->getKey())
                ->where('member_status', 1)
                ->lockForUpdate()
                ->firstOrFail();
            $toLevel = MemberLevel::query()
                ->whereKey($data['to_level_id'])
                ->where('member_level_is_active', 1)
                ->firstOrFail();

            if (! $lockedMember->level) {
                throw new ProcessException('Tingkat mitra saat ini tidak tersedia.');
            }
            $this->ensureValidDowngradeTransition($lockedMember->level, $toLevel);

            $this->ensureNoPendingTransfer($lockedMember);
            $toParent = $this->validParentForLevel(
                (int) $data['to_parent_member_id'],
                $toLevel,
                $lockedMember->getKey()
            );
            $this->ensureNotDescendant($lockedMember, $toParent);
            $directDownlines = Member::query()
                ->with('level')
                ->where('member_parent_member_id', $lockedMember->getKey())
                ->lockForUpdate()
                ->get();

            $effectiveDate = CarbonImmutable::today()->addMonthNoOverflow()->startOfMonth();
            $now = now();
            $batchUuid = (string) Str::uuid();
            $mainTransfer = $this->createScheduledDowngradeTransfer(
                member: $lockedMember,
                toParentId: $toParent->getKey(),
                toLevelId: $toLevel->getKey(),
                effectiveDate: $effectiveDate->toDateString(),
                note: $data['note'],
                now: $now,
                administratorId: $administrator->getKey(),
                batchUuid: $batchUuid,
            );

            foreach ($directDownlines as $downline) {
                if (! $downline->level) {
                    throw new ProcessException('Tingkat mitra bawahan tidak tersedia.');
                }

                if ($this->canLevelHaveParent($downline->level, $toLevel)) {
                    continue;
                }

                if (! $this->canLevelHaveParent($downline->level, $toParent->level)) {
                    throw new ProcessException(
                        "Jaringan bawahan {$downline->member_code} tidak dapat dipindahkan otomatis tanpa merusak hierarki.",
                    );
                }

                $this->createScheduledDowngradeTransfer(
                    member: $downline,
                    toParentId: $toParent->getKey(),
                    toLevelId: $downline->member_member_level_id,
                    effectiveDate: $effectiveDate->toDateString(),
                    note: "Pemindahan downline karena downgrade {$lockedMember->member_code}. {$data['note']}",
                    now: $now,
                    administratorId: $administrator->getKey(),
                    batchUuid: $batchUuid,
                );
            }

            return $this->downgrade($mainTransfer);
        });
    }

    /** @return array{applied: int, skipped: int} */
    public function applyScheduledChanges(): array
    {
        $result = ['applied' => 0, 'skipped' => 0];
        $processedBatches = [];

        MemberNetworkSwitch::query()
            ->where(function (Builder $query): void {
                $query->where('network_switch_status', 'approved')
                    ->orWhere(function (Builder $downgradeQuery): void {
                        $downgradeQuery
                            ->where('network_switch_type', 'downgrade')
                            ->where('network_switch_status', 'scheduled');
                    });
            })
            ->whereDate('network_switch_effective_date', '<=', today())
            ->whereNull('network_switch_applied_datetime')
            ->orderBy('network_switch_transfer_id')
            ->chunkById(100, function (Collection $transfers) use (&$processedBatches, &$result): void {
                foreach ($transfers as $transfer) {
                    $batchUuid = $transfer->network_switch_type === 'downgrade'
                        ? $transfer->network_switch_batch_uuid
                        : null;
                    if ($batchUuid && isset($processedBatches[$batchUuid])) {
                        continue;
                    }
                    if ($batchUuid) {
                        $processedBatches[$batchUuid] = true;
                    }

                    try {
                        $result['applied'] += $batchUuid
                            ? $this->applyNetworkTransferBatch($batchUuid)
                            : $this->applyNetworkTransfer((int) $transfer->getKey());
                    } catch (ProcessException $exception) {
                        $result['skipped'] += $batchUuid
                            ? $this->pendingBatchTransferCount($batchUuid)
                            : 1;
                        Log::warning('Perpindahan jaringan terjadwal gagal diterapkan.', [
                            'network_switch_transfer_id' => $transfer->getKey(),
                            'network_switch_batch_uuid' => $batchUuid,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            }, 'network_switch_transfer_id');

        return $result;
    }

    private function ensureUpgradeCanBeProcessed(MemberUpgradeQualified $upgrade): void
    {
        if ($upgrade->member_upgrade_qualified_status !== 'requested') {
            throw new ProcessException('Kelayakan kenaikan tingkat sudah diproses dan tidak dapat diproses kembali.');
        }
    }

    private function ensureValidUpgradeTransition(MemberLevel $fromLevel, MemberLevel $toLevel): void
    {
        $valid = match ($fromLevel->member_level_code) {
            'RSL' => $toLevel->member_level_code === 'AGT',
            'AGT' => $toLevel->member_level_code === 'DST',
            default => false,
        };

        if (! $valid) {
            throw new ProcessException('Perubahan tingkat pada kelayakan kenaikan tingkat tidak sesuai aturan kemitraan.');
        }
    }

    private function ensureValidDowngradeTransition(MemberLevel $fromLevel, MemberLevel $toLevel): void
    {
        $valid = match ($fromLevel->member_level_code) {
            'DST' => $toLevel->member_level_code === 'AGT',
            'AGT' => $toLevel->member_level_code === 'RSL',
            default => false,
        };

        if (! $valid) {
            throw new ProcessException('Downgrade hanya dapat dilakukan satu tingkat: Distributor ke Agent atau Agent ke Reseller.');
        }
    }

    private function canLevelHaveParent(MemberLevel $memberLevel, ?MemberLevel $parentLevel): bool
    {
        if (! $parentLevel) {
            return false;
        }

        return match ($memberLevel->member_level_code) {
            'DST', 'AGT' => $parentLevel->member_level_code === 'DST',
            // A reseller may remain directly under an agent that has been
            // upgraded to distributor. Purchase and PO seller resolution use
            // this current parent, so the chain remains valid after upgrade.
            'RSL' => in_array($parentLevel->member_level_code, ['AGT', 'DST'], true),
            default => false,
        };
    }

    private function upgradeParentId(Member $member, MemberLevel $toLevel): int
    {
        if ($toLevel->member_level_code === 'DST') {
            if ($member->parent?->level?->member_level_code === 'DST') {
                return (int) $member->parent->getKey();
            }

            return 0;
        }

        $parent = $member->parent;
        if ($toLevel->member_level_code === 'AGT' && $parent?->level?->member_level_code === 'DST') {
            return (int) $parent->getKey();
        }

        if ($toLevel->member_level_code === 'AGT'
            && $parent?->level?->member_level_code === 'AGT'
            && $parent->parent?->level?->member_level_code === 'DST') {
            return (int) $parent->parent->getKey();
        }

        throw new ProcessException('Distributor yang menaungi Agent tidak ditemukan.');
    }

    private function ensureNoPendingTransfer(Member $member): void
    {
        if ($this->hasPendingTransfer($member)) {
            throw new ProcessException('Mitra masih memiliki perpindahan jaringan yang belum diterapkan.');
        }
    }

    private function hasPendingTransfer(Member $member): bool
    {
        return MemberNetworkSwitch::query()
            ->where('network_switch_member_id', $member->getKey())
            ->whereIn('network_switch_status', ['scheduled', 'approved'])
            ->whereNull('network_switch_applied_datetime')
            ->exists();
    }

    private function validParentForLevel(int $parentId, MemberLevel $memberLevel, int $memberId): Member
    {
        if ($parentId === $memberId) {
            throw new ProcessException('Mitra tidak dapat menjadi sponsor bagi dirinya sendiri.');
        }

        $expectedParentCode = $this->expectedParentCode($memberLevel);

        $parent = Member::query()
            ->with('level')
            ->whereKey($parentId)
            ->where('member_status', 1)
            ->first();

        if (! $parent || $parent->level?->member_level_code !== $expectedParentCode) {
            throw new ProcessException("Sponsor baru harus merupakan {$this->levelLabel($expectedParentCode)} aktif.");
        }
        if ($this->hasPendingTransfer($parent)) {
            throw new ProcessException('Sponsor baru masih memiliki perpindahan jaringan yang belum diterapkan.');
        }

        return $parent;
    }

    private function expectedParentCode(MemberLevel $memberLevel): string
    {
        return match ($memberLevel->member_level_code) {
            'AGT' => 'DST',
            'RSL' => 'AGT',
            default => throw new ProcessException('Tingkat mitra tersebut tidak boleh memiliki sponsor.'),
        };
    }

    /** @return list<int> */
    private function descendantIds(Member $member): array
    {
        $descendantIds = [];
        $parentIds = [(int) $member->getKey()];

        for ($depth = 0; $depth < 100 && $parentIds !== []; $depth++) {
            $childIds = Member::query()
                ->whereIn('member_parent_member_id', $parentIds)
                ->pluck('member_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
            $childIds = array_values(array_diff($childIds, $descendantIds));
            if ($childIds === []) {
                break;
            }

            $descendantIds = [...$descendantIds, ...$childIds];
            $parentIds = $childIds;
        }

        return $descendantIds;
    }

    private function ensureNotDescendant(Member $member, Member $candidateParent): void
    {
        $current = $candidateParent;
        for ($depth = 0; $depth < 100 && (int) $current->member_parent_member_id !== 0; $depth++) {
            if ((int) $current->member_parent_member_id === (int) $member->getKey()) {
                throw new ProcessException('Sponsor baru tidak boleh berasal dari jaringan di bawah mitra yang dipindahkan.');
            }

            $current = Member::query()->find($current->member_parent_member_id);
            if (! $current) {
                return;
            }
        }
    }

    private function createScheduledDowngradeTransfer(
        Member $member,
        int $toParentId,
        int $toLevelId,
        string $effectiveDate,
        string $note,
        \DateTimeInterface $now,
        int $administratorId,
        string $batchUuid,
    ): MemberNetworkSwitch {
        $this->ensureNoPendingTransfer($member);

        return MemberNetworkSwitch::query()->create([
            'network_switch_batch_uuid' => $batchUuid,
            'network_switch_upgrade_qualified_id' => 0,
            'network_switch_member_id' => $member->getKey(),
            'network_switch_from_parent_member_id' => $member->member_parent_member_id,
            'network_switch_to_parent_member_id' => $toParentId,
            'network_switch_from_level_id' => $member->member_member_level_id,
            'network_switch_to_level_id' => $toLevelId,
            'network_switch_type' => 'downgrade',
            // Downgrade is scheduled directly from the form; it has no
            // separate approve/reject action.
            'network_switch_status' => 'scheduled',
            'network_switch_admin_id' => $administratorId,
            'network_switch_approved_datetime' => $now,
            'network_switch_effective_date' => $effectiveDate,
            'network_switch_transfer_note' => $note,
            'network_switch_created_datetime' => $now,
        ]);
    }

    private function applyNetworkTransferBatch(string $batchUuid): int
    {
        return DB::transaction(function () use ($batchUuid): int {
            $transfers = MemberNetworkSwitch::query()
                ->where('network_switch_batch_uuid', $batchUuid)
                ->orderBy('network_switch_transfer_id')
                ->lockForUpdate()
                ->get();

            if ($transfers->isEmpty() || $transfers->every(
                fn (MemberNetworkSwitch $transfer): bool => $transfer->network_switch_applied_datetime !== null,
            )) {
                return 0;
            }

            if ($transfers->contains(
                fn (MemberNetworkSwitch $transfer): bool => $transfer->network_switch_type !== 'downgrade'
                    || $transfer->network_switch_status !== 'scheduled'
                    || $transfer->network_switch_applied_datetime !== null
                    || $transfer->network_switch_effective_date?->isFuture(),
            )) {
                throw new ProcessException('Seluruh jaringan downgrade harus dipindahkan dalam satu proses.');
            }

            foreach ($transfers as $transfer) {
                $this->applyNetworkTransfer((int) $transfer->getKey());
            }

            return $transfers->count();
        });
    }

    private function pendingBatchTransferCount(string $batchUuid): int
    {
        return max(1, MemberNetworkSwitch::query()
            ->where('network_switch_batch_uuid', $batchUuid)
            ->whereNull('network_switch_applied_datetime')
            ->count());
    }

    private function applyNetworkTransfer(int $transferId): int
    {
        return DB::transaction(function () use ($transferId): int {
            $transfer = MemberNetworkSwitch::query()
                ->whereKey($transferId)
                ->lockForUpdate()
                ->firstOrFail();

            $canApply = $transfer->network_switch_status === 'approved'
                || ($transfer->network_switch_type === 'downgrade'
                    && $transfer->network_switch_status === 'scheduled');

            if (! $canApply
                || $transfer->network_switch_applied_datetime !== null
                || $transfer->network_switch_effective_date?->isFuture()) {
                return 0;
            }

            $member = Member::query()
                ->whereKey($transfer->network_switch_member_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $member->member_parent_member_id !== (int) $transfer->network_switch_from_parent_member_id
                || (int) $member->member_member_level_id !== (int) $transfer->network_switch_from_level_id) {
                throw new ProcessException('Sponsor atau tingkat mitra sudah berubah dari jadwal perpindahan.');
            }

            $previousPeriod = $transfer->network_switch_effective_date->copy()->subMonth();
            $periodClosed = MemberAchievement::query()
                ->where('member_achievement_member_id', $member->getKey())
                ->where('member_achievement_year', $previousPeriod->year)
                ->where('member_achievement_month', $previousPeriod->month)
                ->exists();
            if (! $periodClosed) {
                throw new ProcessException('Periode reward sebelumnya belum ditutup untuk mitra ini.');
            }

            $uplineLevelId = (int) Member::query()
                ->whereKey($transfer->network_switch_to_parent_member_id)
                ->value('member_member_level_id');
            $member->update([
                'member_parent_member_id' => $transfer->network_switch_to_parent_member_id,
                'member_member_level_id' => $transfer->network_switch_to_level_id,
            ]);
            $transfer->update(['network_switch_applied_datetime' => now()]);

            MemberHistory::query()->create([
                'member_history_member_id' => $member->getKey(),
                'member_history_upgrade_qualified_id' => $transfer->network_switch_upgrade_qualified_id,
                'member_history_network_transfer_id' => $transfer->getKey(),
                'member_history_action' => $transfer->network_switch_type,
                'member_history_from_level_id' => $transfer->network_switch_from_level_id,
                'member_history_to_level_id' => $transfer->network_switch_to_level_id,
                'member_history_upline_member_id' => $transfer->network_switch_to_parent_member_id,
                'member_history_upline_member_level_id' => $uplineLevelId,
                'member_history_downline_member_id' => 0,
                'member_history_reason' => $transfer->network_switch_transfer_note,
                'member_history_approved_by' => $transfer->network_switch_admin_id,
                'member_history_datetime' => now(),
            ]);

            if ((int) $transfer->network_switch_upgrade_qualified_id > 0) {
                $qualification = MemberUpgradeQualified::query()
                    ->whereKey($transfer->network_switch_upgrade_qualified_id)
                    ->lockForUpdate()
                    ->first();
                $hasPendingTransfers = MemberNetworkSwitch::query()
                    ->where(
                        'network_switch_upgrade_qualified_id',
                        $transfer->network_switch_upgrade_qualified_id
                    )
                    ->whereNull('network_switch_applied_datetime')
                    ->exists();

                if ($qualification && ! $hasPendingTransfers) {
                    $qualification->update([
                        'member_upgrade_qualified_status' => 'applied',
                        'member_upgrade_qualified_applied_datetime' => now(),
                        'member_upgrade_qualified_last_update_datetime' => now(),
                    ]);
                }
            }

            return 1;
        });
    }

    private function levelLabel(string $code): string
    {
        return match ($code) {
            'DST' => 'Distributor',
            'AGT' => 'Agent',
            'RSL' => 'Reseller',
            default => $code,
        };
    }

    private function upgradeDetailQuery(): Builder
    {
        return MemberUpgradeQualified::query()->with([
            'member.level',
            'member.achievements' => fn ($query) => $query
                ->orderByDesc('member_achievement_year')
                ->orderByDesc('member_achievement_month'),
            'fromLevel',
            'toLevel',
            'administrator',
            'networkTransfers',
        ]);
    }

    private function downgradeDetailQuery(): Builder
    {
        return MemberNetworkSwitch::query()->with([
            'member.level',
            'fromParent.level',
            'toParent.level',
            'fromLevel',
            'toLevel',
            'administrator',
        ]);
    }
}
