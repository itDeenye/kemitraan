<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberHistory;
use App\Models\MemberLevel;
use App\Models\MemberNetworkSwitch;
use App\Models\MemberRegistration;
use App\Models\MemberUpgradeQualified;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DevelopmentPartnershipDataSeeder extends Seeder
{
    public function run(): void
    {
        $distributor = Member::query()->findOrFail(1);
        $agent = Member::query()->where('member_mobilephone', '+6281200000101')->firstOrFail();
        $secondAgent = Member::query()->where('member_mobilephone', '+6281200000102')->firstOrFail();
        $reseller = Member::query()->where('member_mobilephone', '+6281200000201')->firstOrFail();
        $account = MemberAccount::query()->where('member_account_member_id', 1)->firstOrFail();
        $levels = MemberLevel::query()->get()->keyBy('member_level_code');
        $location = $this->location();
        $bank = DB::table('ref_bank')->orderBy('bank_id')->first();
        $now = now();

        DB::transaction(function () use (
            $account,
            $agent,
            $bank,
            $distributor,
            $levels,
            $location,
            $now,
            $reseller,
            $secondAgent,
        ): void {
            $this->registrations(
                $account,
                $distributor,
                (int) $levels['AGT']->getKey(),
                $location,
                $bank,
                $now,
            );
            $this->upgrade(
                $reseller,
                (int) $levels['RSL']->getKey(),
                (int) $levels['AGT']->getKey(),
                $now,
            );
            $this->downgrade(
                $agent,
                $distributor,
                $secondAgent,
                (int) $levels['AGT']->getKey(),
                (int) $levels['RSL']->getKey(),
                $now,
            );
            $this->history($secondAgent, $distributor, $now);
        });
    }

    /** @param array<string, int> $location */
    private function registrations(
        MemberAccount $account,
        Member $distributor,
        int $agentLevelId,
        array $location,
        ?object $bank,
        CarbonInterface $now,
    ): void {
        $base = [
            'member_registration_member_id' => 0,
            'member_registration_gender' => 'Perempuan',
            'member_registration_birth_date' => '1995-05-10',
            'member_registration_address' => 'Jalan Calon Mitra Development Nomor 5',
            'member_registration_subdistrict_id' => $location['subdistrict_id'],
            'member_registration_district_id' => $location['district_id'],
            'member_registration_city_id' => $location['city_id'],
            'member_registration_province_id' => $location['province_id'],
            'member_registration_country_id' => 1,
            'member_registration_bank_id' => (int) ($bank?->bank_id ?? 0),
            'member_registration_bank_name' => $bank?->bank_name,
            'member_registration_bank_account_name' => 'Calon Mitra Development',
            'member_registration_bank_account_no' => '880000000099',
            'member_registration_bank_city' => 'Surabaya',
            'member_registration_bank_branch' => 'Development',
            'member_registration_identity_type' => 'KTP',
            'member_registration_identity_image' => '/storage/media/development/dny-development.png',
            'member_registration_identity_image_filename' => 'dny-development.png',
            'member_registration_nib' => null,
            'member_registration_password' => $account->member_account_password,
            'member_registration_status' => 'requested',
            'member_registration_status_administrator_id' => 0,
            'member_registration_status_datetime' => null,
            'member_registration_note' => 'Data pendaftaran development.',
            'member_registration_datetime' => $now->copy()->subDays(2),
        ];

        MemberRegistration::query()->updateOrCreate(
            ['member_registration_mobilephone' => '+6281500000001'],
            $base + [
                'member_registration_member_level_id' => $agentLevelId,
                'member_registration_upline_member_id' => $distributor->getKey(),
                'member_registration_name' => 'Calon Agent Development',
                'member_registration_email' => 'calon.agent@dny.example.test',
                'member_registration_mobilephone' => '+6281500000001',
                'member_registration_identity_no' => '3578000000000001',
                'member_registration_username' => '',
            ],
        );
        MemberRegistration::query()
            ->where('member_registration_mobilephone', '+6281500000002')
            ->delete();
    }

    private function upgrade(
        Member $reseller,
        int $fromLevelId,
        int $toLevelId,
        CarbonInterface $now,
    ): MemberUpgradeQualified {
        $toPeriodDate = $now->copy()->subMonth()->startOfMonth();
        $fromPeriod = (int) $toPeriodDate->copy()->subMonths(2)->format('ym');
        $toPeriod = (int) $toPeriodDate->format('ym');

        MemberUpgradeQualified::query()
            ->where('member_upgrade_qualified_member_id', $reseller->getKey())
            ->where(function ($query): void {
                $query->where('member_upgrade_qualified_from_year_month', '>', 9999)
                    ->orWhere('member_upgrade_qualified_to_year_month', '>', 9999);
            })
            ->delete();

        return MemberUpgradeQualified::query()->updateOrCreate(
            [
                'member_upgrade_qualified_member_id' => $reseller->getKey(),
                'member_upgrade_qualified_from_year_month' => $fromPeriod,
                'member_upgrade_qualified_to_year_month' => $toPeriod,
            ],
            [
                'member_upgrade_qualified_from_level_id' => $fromLevelId,
                'member_upgrade_qualified_to_level_id' => $toLevelId,
                'member_upgrade_qualified_status' => 'requested',
                'member_upgrade_qualified_admin_id' => 0,
                'member_upgrade_qualified_approved_datetime' => null,
                'member_upgrade_qualified_effective_date' => null,
                'member_upgrade_qualified_applied_datetime' => null,
                'member_upgrade_qualified_last_update_datetime' => $now,
                'member_upgrade_qualified_created_datetime' => $now->copy()->subDay(),
            ],
        );
    }

    private function downgrade(
        Member $agent,
        Member $fromParent,
        Member $toParent,
        int $fromLevelId,
        int $toLevelId,
        CarbonInterface $now,
    ): MemberNetworkSwitch {
        return MemberNetworkSwitch::query()->updateOrCreate(
            [
                'network_switch_member_id' => $agent->getKey(),
                'network_switch_type' => 'downgrade',
                'network_switch_transfer_note' => 'Data jadwal downgrade development.',
            ],
            [
                'network_switch_upgrade_qualified_id' => 0,
                'network_switch_from_parent_member_id' => $fromParent->getKey(),
                'network_switch_to_parent_member_id' => $toParent->getKey(),
                'network_switch_from_level_id' => $fromLevelId,
                'network_switch_to_level_id' => $toLevelId,
                'network_switch_status' => 'scheduled',
                'network_switch_admin_id' => 0,
                'network_switch_approved_datetime' => null,
                'network_switch_effective_date' => $now->copy()->addMonthNoOverflow()->startOfMonth(),
                'network_switch_applied_datetime' => null,
                'network_switch_created_datetime' => $now->copy()->subHours(6),
            ],
        );
    }

    private function history(
        Member $member,
        Member $upline,
        CarbonInterface $now,
    ): void {
        MemberHistory::query()->updateOrCreate(
            [
                'member_history_member_id' => $member->getKey(),
                'member_history_action' => 'register',
                'member_history_reason' => 'Histori registrasi data development.',
            ],
            [
                'member_history_upgrade_qualified_id' => 0,
                'member_history_network_transfer_id' => 0,
                'member_history_from_level_id' => 0,
                'member_history_to_level_id' => $member->member_member_level_id,
                'member_history_upline_member_id' => $upline->getKey(),
                'member_history_upline_member_level_id' => $upline->member_member_level_id,
                'member_history_downline_member_id' => 0,
                'member_history_approved_by' => 1,
                'member_history_datetime' => $now->copy()->subMonths(2),
            ],
        );
    }

    /** @return array{province_id: int, city_id: int, district_id: int, subdistrict_id: int} */
    private function location(): array
    {
        $provinceId = (int) DB::table('ref_province')->orderBy('province_id')->value('province_id');
        $cityId = (int) DB::table('ref_city')
            ->where('city_province_id', $provinceId)
            ->orderBy('city_id')
            ->value('city_id');
        $districtId = (int) DB::table('ref_district')
            ->where('district_city_id', $cityId)
            ->orderBy('district_id')
            ->value('district_id');
        $subdistrictId = (int) DB::table('ref_subdistrict')
            ->where('subdistrict_district_id', $districtId)
            ->orderBy('subdistrict_id')
            ->value('subdistrict_id');

        return [
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'district_id' => $districtId,
            'subdistrict_id' => $subdistrictId,
        ];
    }
}
