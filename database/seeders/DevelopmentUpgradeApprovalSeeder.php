<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\MemberAchievement;
use App\Models\MemberLevel;
use App\Models\MemberUpgradeQualified;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class DevelopmentUpgradeApprovalSeeder extends Seeder
{
    public function run(?int $memberId = null): void
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            $this->command?->warn(
                'DevelopmentUpgradeApprovalSeeder hanya dapat dijalankan pada environment local/development.'
            );

            return;
        }

        $result = DB::transaction(function () use ($memberId): array {
            $resellerLevel = MemberLevel::query()
                ->where('member_level_code', 'RSL')
                ->where('member_level_is_active', 1)
                ->firstOrFail();
            $agentLevel = MemberLevel::query()
                ->where('member_level_code', 'AGT')
                ->where('member_level_is_active', 1)
                ->firstOrFail();

            if (! $memberId) {
                throw new LogicException(
                    'ID Reseller wajib diberikan melalui command development:seed-upgrade-approval.'
                );
            }

            $reseller = $this->eligibleReseller($memberId, $resellerLevel);
            $periodEnd = CarbonImmutable::now(config('app.timezone'))
                ->subMonth()
                ->startOfMonth();
            $periodStart = $periodEnd->subMonths(2);
            $fromPeriod = (int) $periodStart->format('ym');
            $toPeriod = (int) $periodEnd->format('ym');
            $qualification = MemberUpgradeQualified::query()
                ->where('member_upgrade_qualified_member_id', $reseller->getKey())
                ->where('member_upgrade_qualified_from_level_id', $resellerLevel->getKey())
                ->where('member_upgrade_qualified_to_level_id', $agentLevel->getKey())
                ->where('member_upgrade_qualified_from_year_month', $fromPeriod)
                ->where('member_upgrade_qualified_to_year_month', $toPeriod)
                ->first();

            if ($qualification && in_array(
                $qualification->member_upgrade_qualified_status,
                ['approved', 'scheduled', 'applied'],
                true,
            )) {
                throw new LogicException(
                    'Upgrade Reseller tersebut sudah disetujui atau sedang dijadwalkan.'
                );
            }

            if (! $qualification) {
                $qualification = MemberUpgradeQualified::query()
                    ->where('member_upgrade_qualified_member_id', $reseller->getKey())
                    ->where('member_upgrade_qualified_status', 'requested')
                    ->latest('member_upgrade_qualified_id')
                    ->first();
            }

            if ($qualification) {
                $qualification->update([
                    'member_upgrade_qualified_from_level_id' => $resellerLevel->getKey(),
                    'member_upgrade_qualified_to_level_id' => $agentLevel->getKey(),
                    'member_upgrade_qualified_from_year_month' => $fromPeriod,
                    'member_upgrade_qualified_to_year_month' => $toPeriod,
                    'member_upgrade_qualified_status' => 'requested',
                    'member_upgrade_qualified_admin_id' => 0,
                    'member_upgrade_qualified_approved_datetime' => null,
                    'member_upgrade_qualified_effective_date' => null,
                    'member_upgrade_qualified_applied_datetime' => null,
                    'member_upgrade_qualified_last_update_datetime' => now(),
                ]);
            } else {
                $qualification = MemberUpgradeQualified::query()->create([
                    'member_upgrade_qualified_member_id' => $reseller->getKey(),
                    'member_upgrade_qualified_from_level_id' => $resellerLevel->getKey(),
                    'member_upgrade_qualified_to_level_id' => $agentLevel->getKey(),
                    'member_upgrade_qualified_from_year_month' => $fromPeriod,
                    'member_upgrade_qualified_to_year_month' => $toPeriod,
                    'member_upgrade_qualified_status' => 'requested',
                    'member_upgrade_qualified_admin_id' => 0,
                    'member_upgrade_qualified_approved_datetime' => null,
                    'member_upgrade_qualified_effective_date' => null,
                    'member_upgrade_qualified_applied_datetime' => null,
                    'member_upgrade_qualified_last_update_datetime' => now(),
                    'member_upgrade_qualified_created_datetime' => now()->subDay(),
                ]);
            }

            foreach ([50, 55, 60] as $index => $point) {
                $period = $periodStart->addMonths($index);

                MemberAchievement::query()->updateOrCreate(
                    [
                        'member_achievement_member_id' => $reseller->getKey(),
                        'member_achievement_year' => $period->year,
                        'member_achievement_month' => $period->month,
                    ],
                    [
                        'member_achievement_point' => $point,
                        'member_achievement_customer_count' => 3,
                        'member_achievement_total_trx_amount' => $point * 150_000,
                        'member_achievement_create_datetime' => $period->endOfMonth()->endOfDay(),
                    ],
                );
            }

            return [
                'member' => $reseller,
                'qualification' => $qualification,
            ];
        });

        /** @var Member $reseller */
        $reseller = $result['member'];
        /** @var MemberUpgradeQualified $qualification */
        $qualification = $result['qualification'];

        $this->command?->info(
            "Skenario approval upgrade berhasil disiapkan untuk {$reseller->member_code} "
            ."({$reseller->member_name}), Reseller menjadi Agent."
        );
        $this->command?->line(
            "ID approval: {$qualification->getKey()}; status: requested."
        );
    }

    private function eligibleReseller(int $memberId, MemberLevel $resellerLevel): Member
    {
        $reseller = Member::query()
            ->with(['level', 'parent.level', 'parent.parent.level'])
            ->whereKey($memberId)
            ->where('member_status', 1)
            ->first();

        if (! $reseller) {
            throw new LogicException(
                "Mitra aktif dengan ID {$memberId} tidak ditemukan."
            );
        }

        if ((int) $reseller->member_member_level_id !== (int) $resellerLevel->getKey()) {
            throw new LogicException("Mitra ID {$memberId} harus berlevel Reseller.");
        }

        if ($reseller->parent?->level?->member_level_code !== 'AGT'
            || $reseller->parent?->parent?->level?->member_level_code !== 'DST') {
            throw new LogicException(
                'Reseller harus berada pada jalur Agent ke Distributor.'
            );
        }

        if ($reseller->networkTransfers()
            ->whereIn('network_switch_status', ['scheduled', 'approved'])
            ->whereNull('network_switch_applied_datetime')
            ->exists()) {
            throw new LogicException(
                'Reseller masih memiliki perubahan level atau jaringan yang belum diterapkan.'
            );
        }

        return $reseller;
    }
}
