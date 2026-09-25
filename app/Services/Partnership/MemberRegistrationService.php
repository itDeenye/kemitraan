<?php

namespace App\Services\Partnership;

use App\Exceptions\ProcessException;
use App\Libraries\DataTable;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberAddress;
use App\Models\MemberBankAccount;
use App\Models\MemberGroup;
use App\Models\MemberHistory;
use App\Models\MemberLevel;
use App\Models\MemberNetworkSwitch;
use App\Models\MemberRegistration;
use App\Models\RefBank;
use App\Models\SiteAdministrator;
use App\Services\Notification\PartnershipEmailService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MemberRegistrationService
{
    public function __construct(
        private readonly MemberCodeService $memberCodeService,
        private readonly MemberPasswordService $memberPasswordService,
        private readonly PartnershipEmailService $partnershipEmailService,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function memberRegistrations(array $params, int $sponsorId): array
    {
        return $this->registrationList($params, $sponsorId);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function adminRegistrations(array $params): array
    {
        return $this->registrationList($params);
    }

    public function memberOptions(Member $sponsor): array
    {
        $targetLevel = $this->targetLevelForSponsor($sponsor);
        $sponsor = [
            'id' => $sponsor->member_id,
            'code' => $sponsor->member_code,
            'name' => $sponsor->member_name,
            'level_code' => $sponsor->level?->member_level_code,
        ];

        return [
            'target_level' => [
                'id' => (int) $targetLevel->getKey(),
                'code' => $targetLevel->member_level_code,
                'name' => $targetLevel->member_level_name,
            ],
            'sponsor' => $sponsor,
            'genders' => ['Laki-laki', 'Perempuan'],
            'identity_types' => ['KTP', 'SIM', 'PASPOR'],
        ];
    }

    public function adminOptions(): array
    {
        return $this->registrationOptions(['DST']);
    }

    public function memberRegistration(
        MemberRegistration $registration,
        int $sponsorId
    ): MemberRegistration {
        return $this->detailQuery()
            ->whereKey($registration->getKey())
            ->where('member_registration_upline_member_id', $sponsorId)
            ->firstOrFail();
    }

    public function adminRegistration(MemberRegistration $registration): MemberRegistration
    {
        return $this->detailQuery()->findOrFail($registration->getKey());
    }

    /** @param array<string, mixed> $data */
    public function createByMember(MemberAccount $account, array $data): MemberRegistration
    {
        $sponsor = Member::query()
            ->with('level')
            ->whereKey($account->member_account_member_id)
            ->where('member_status', 1)
            ->firstOrFail();
        $targetLevel = $this->targetLevelForSponsor($sponsor);

        $this->ensureSponsorCanRecruit($sponsor, $targetLevel);

        return $this->createRegistration($data, $targetLevel, $sponsor);
    }

    /** @param array<string, mixed> $data */
    public function createDistributor(array $data): MemberRegistration
    {
        $distributorLevel = MemberLevel::query()
            ->where('member_level_code', 'DST')
            ->where('member_level_is_active', 1)
            ->first();

        if (! $distributorLevel) {
            throw new ProcessException('Tingkat Distributor aktif belum tersedia.');
        }

        return $this->createRegistration($data, $distributorLevel);
    }

    public function approve(
        MemberRegistration $registration,
        SiteAdministrator $administrator,
        ?string $note
    ): MemberRegistration {
        /** @var array{registration: MemberRegistration, member: Member, password: string} $result */
        $result = DB::transaction(function () use ($registration, $administrator, $note): array {
            $lockedRegistration = MemberRegistration::query()
                ->whereKey($registration->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->ensureRequested($lockedRegistration);

            $targetLevel = MemberLevel::query()
                ->whereKey($lockedRegistration->member_registration_member_level_id)
                ->where('member_level_is_active', 1)
                ->first();

            if (! $targetLevel) {
                throw new ProcessException('Tingkat mitra pada pengajuan tidak aktif atau tidak tersedia.');
            }

            $sponsor = $this->approvalSponsor($lockedRegistration, $targetLevel);
            $this->ensureApplicantStillUnique($lockedRegistration);
            $group = MemberGroup::query()
                ->where('member_group_name', $targetLevel->member_level_name)
                ->where('member_group_is_active', 1)
                ->first();

            if (! $group) {
                throw new ProcessException('Grup akses untuk tingkat mitra belum tersedia atau tidak aktif.');
            }

            $plainPassword = $this->memberPasswordService->approvalPassword(
                $lockedRegistration->member_registration_birth_date,
            );
            $member = Member::query()->create($this->memberAttributes(
                $lockedRegistration,
                $targetLevel,
                $sponsor
            ));
            MemberAccount::query()->create([
                'member_account_member_id' => $member->getKey(),
                'member_account_member_group_id' => $group->getKey(),
                'member_account_username' => $member->member_code,
                'member_account_password' => Hash::make($plainPassword),
                'member_account_pin' => '',
            ]);
            $this->createDefaultAddress($member, $lockedRegistration);
            $this->createBankAccount($member, $lockedRegistration);
            MemberHistory::query()->create([
                'member_history_member_id' => $member->getKey(),
                'member_history_action' => 'register',
                'member_history_from_level_id' => 0,
                'member_history_to_level_id' => $targetLevel->getKey(),
                'member_history_upline_member_id' => $sponsor?->getKey() ?? 0,
                'member_history_upline_member_level_id' => $sponsor?->member_member_level_id ?? 0,
                'member_history_downline_member_id' => 0,
                'member_history_reason' => $note ?: 'Registrasi member disetujui.',
                'member_history_approved_by' => $administrator->getKey(),
                'member_history_datetime' => now(),
            ]);

            $lockedRegistration->update([
                'member_registration_member_id' => $member->getKey(),
                'member_registration_username' => $member->member_code,
                'member_registration_status' => 'approved',
                'member_registration_status_administrator_id' => $administrator->getKey(),
                'member_registration_status_datetime' => now(),
                'member_registration_note' => $note,
            ]);

            return [
                'registration' => $this->adminRegistration($lockedRegistration),
                'member' => $member,
                'password' => $plainPassword,
            ];
        });
        $this->partnershipEmailService->sendRegistrationApproved(
            $result['registration'],
            $result['member'],
            $result['member']->member_code,
            $result['password'],
        );

        return $result['registration'];
    }

    public function reject(
        MemberRegistration $registration,
        SiteAdministrator $administrator,
        string $note
    ): MemberRegistration {
        $rejectedRegistration = DB::transaction(function () use ($registration, $administrator, $note): MemberRegistration {
            $lockedRegistration = MemberRegistration::query()
                ->whereKey($registration->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->ensureRequested($lockedRegistration);
            $lockedRegistration->update([
                'member_registration_status' => 'rejected',
                'member_registration_status_administrator_id' => $administrator->getKey(),
                'member_registration_status_datetime' => now(),
                'member_registration_note' => $note,
            ]);

            return $this->adminRegistration($lockedRegistration);
        });
        $this->partnershipEmailService->sendRegistrationRejected($rejectedRegistration);

        return $rejectedRegistration;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    private function registrationList(array $params, ?int $sponsorId = null): array
    {
        $registrationTable = (new MemberRegistration)->getTable();
        $levelTable = (new MemberLevel)->getTable();
        $memberTable = (new Member)->getTable();
        $administratorTable = (new SiteAdministrator)->getTable();
        $query = DataTable::select([
            "{$registrationTable}.member_registration_id as id",
            "{$registrationTable}.member_registration_member_level_id as level_id",
            "{$levelTable}.member_level_code as level_code",
            "{$levelTable}.member_level_name as level_name",
            "{$registrationTable}.member_registration_upline_member_id as sponsor_id",
            "{$memberTable}.member_code as sponsor_code",
            "{$memberTable}.member_name as sponsor_name",
            "{$registrationTable}.member_registration_name as name",
            "{$registrationTable}.member_registration_email as email",
            "{$registrationTable}.member_registration_mobilephone as mobile_phone",
            "{$registrationTable}.member_registration_username as username",
            "{$registrationTable}.member_registration_identity_no as identity_no",
            "{$registrationTable}.member_registration_status as status",
            "{$registrationTable}.member_registration_status_administrator_id as processed_by_id",
            "{$administratorTable}.administrator_name as processed_by_name",
            "{$registrationTable}.member_registration_datetime as submitted_at",
            "{$registrationTable}.member_registration_status_datetime as processed_at",
        ])
            ->from($registrationTable)
            ->leftJoin($levelTable, "{$levelTable}.member_level_id = {$registrationTable}.member_registration_member_level_id")
            ->leftJoin($memberTable, "{$memberTable}.member_id = {$registrationTable}.member_registration_upline_member_id")
            ->leftJoin(
                $administratorTable,
                "{$administratorTable}.administrator_id = {$registrationTable}.member_registration_status_administrator_id"
            );

        if ($sponsorId !== null) {
            $query->where("{$registrationTable}.member_registration_upline_member_id", $sponsorId);
        }

        return $query
            ->search(['name', 'email', 'mobile_phone', 'username', 'identity_no', 'sponsor_code', 'sponsor_name'])
            ->defaultSort('-id')
            ->get($params);
    }

    /** @param array<int, string> $levelCodes */
    private function registrationOptions(array $levelCodes): array
    {
        return [
            'levels' => MemberLevel::query()
                ->select([
                    'member_level_id as id',
                    'member_level_code as code',
                    'member_level_name as name',
                ])
                ->whereIn('member_level_code', $levelCodes)
                ->where('member_level_is_active', 1)
                ->orderBy('member_level_sort_order')
                ->get(),
            'genders' => ['Laki-laki', 'Perempuan'],
            'identity_types' => ['KTP', 'SIM', 'PASPOR'],
        ];
    }

    /** @param array<string, mixed> $data */
    private function createRegistration(
        array $data,
        MemberLevel $targetLevel,
        ?Member $sponsor = null
    ): MemberRegistration {
        $bankName = isset($data['bank_id'])
            ? (string) RefBank::query()->where('bank_id', $data['bank_id'])->value('bank_name')
            : null;
        $registration = MemberRegistration::query()->create([
            'member_registration_member_level_id' => $targetLevel->getKey(),
            'member_registration_upline_member_id' => $sponsor?->getKey() ?? 0,
            'member_registration_name' => $data['name'],
            'member_registration_email' => $data['email'] ?? '',
            'member_registration_mobilephone' => $data['mobile_phone'],
            'member_registration_gender' => $data['gender'],
            'member_registration_birth_date' => $data['birth_date'],
            'member_registration_address' => $data['address'],
            'member_registration_province_id' => $data['province_id'],
            'member_registration_city_id' => $data['city_id'],
            'member_registration_district_id' => $data['district_id'],
            'member_registration_subdistrict_id' => $data['subdistrict_id'],
            'member_registration_country_id' => $data['country_id'] ?? 1,
            'member_registration_bank_id' => $data['bank_id'] ?? 0,
            'member_registration_bank_name' => $bankName,
            'member_registration_bank_account_name' => $data['bank_account_name'] ?? null,
            'member_registration_bank_account_no' => $data['bank_account_number'] ?? null,
            'member_registration_bank_city' => $data['bank_city'] ?? null,
            'member_registration_bank_branch' => $data['bank_branch'] ?? null,
            'member_registration_identity_type' => $data['identity_type'],
            'member_registration_identity_no' => $data['identity_no'],
            'member_registration_identity_image' => $data['identity_image_url'] ?? null,
            'member_registration_identity_image_filename' => '',
            'member_registration_nib' => $data['nib'] ?? null,
            'member_registration_username' => '',
            // A temporary hash keeps legacy schema compatibility. The real
            // credential is generated only when an administrator approves.
            'member_registration_password' => Hash::make((string) random_int(10000000, 99999999)),
            'member_registration_status' => 'requested',
            'member_registration_status_administrator_id' => 0,
            'member_registration_note' => null,
            'member_registration_datetime' => now(),
        ]);

        return $this->adminRegistration($registration);
    }

    private function approvalSponsor(
        MemberRegistration $registration,
        MemberLevel $targetLevel
    ): ?Member {
        if ($targetLevel->member_level_code === 'DST') {
            if ((int) $registration->member_registration_upline_member_id !== 0) {
                throw new ProcessException('Distributor tidak boleh memiliki sponsor.');
            }

            return null;
        }

        $sponsor = Member::query()
            ->with('level')
            ->whereKey($registration->member_registration_upline_member_id)
            ->where('member_status', 1)
            ->lockForUpdate()
            ->first();

        if (! $sponsor) {
            throw new ProcessException('Sponsor tidak aktif atau tidak tersedia.');
        }

        $this->ensureSponsorCanRecruit($sponsor, $targetLevel);

        return $sponsor;
    }

    private function ensureSponsorCanRecruit(Member $sponsor, MemberLevel $targetLevel): void
    {
        if ($sponsor->member_status !== 1 || ! $sponsor->level?->member_level_is_active) {
            throw new ProcessException('Sponsor tidak aktif atau tidak tersedia.');
        }

        $hasPendingDowngrade = MemberNetworkSwitch::query()
            ->where('network_switch_member_id', $sponsor->getKey())
            ->where('network_switch_type', 'downgrade')
            ->whereIn('network_switch_status', ['scheduled', 'approved'])
            ->whereNull('network_switch_applied_datetime')
            ->exists();

        if ($hasPendingDowngrade) {
            throw new ProcessException('Mitra yang sedang dijadwalkan turun tingkat tidak dapat menerima pendaftaran mitra baru.');
        }

        if (! in_array($targetLevel->member_level_code, $this->allowedTargetLevelCodes($sponsor), true)) {
            throw new ProcessException('Sponsor tidak memiliki hak untuk mendaftarkan tingkat mitra tersebut.');
        }
    }

    /** @return array<int, string> */
    private function allowedTargetLevelCodes(Member $sponsor): array
    {
        return match ($sponsor->level?->member_level_code) {
            'DST' => ['AGT'],
            'AGT' => ['RSL'],
            default => [],
        };
    }

    private function targetLevelForSponsor(Member $sponsor): MemberLevel
    {
        $targetCode = $this->allowedTargetLevelCodes($sponsor)[0] ?? null;

        if (! $targetCode) {
            throw new ProcessException('Hanya Distributor dan Agent yang dapat mendaftarkan mitra.', 403);
        }

        $targetLevel = MemberLevel::query()
            ->where('member_level_code', $targetCode)
            ->where('member_level_is_active', 1)
            ->first();

        if (! $targetLevel) {
            throw new ProcessException('Tingkat mitra tujuan yang aktif belum tersedia.');
        }

        return $targetLevel;
    }

    private function ensureRequested(MemberRegistration $registration): void
    {
        if ($registration->member_registration_status !== 'requested') {
            throw new ProcessException('Pengajuan sudah diproses dan tidak dapat diproses kembali.');
        }
    }

    private function ensureApplicantStillUnique(MemberRegistration $registration): void
    {
        if (Member::query()
            ->where('member_mobilephone', $registration->member_registration_mobilephone)
            ->where('member_status', '!=', 3)
            ->exists()) {
            throw new ProcessException('Nomor WhatsApp calon mitra sudah digunakan.');
        }

        if (Member::query()
            ->where('member_identity_no', $registration->member_registration_identity_no)
            ->where('member_status', '!=', 3)
            ->exists()) {
            throw new ProcessException('Nomor identitas calon mitra sudah digunakan.');
        }
    }

    /** @return array<string, mixed> */
    private function memberAttributes(
        MemberRegistration $registration,
        MemberLevel $targetLevel,
        ?Member $sponsor
    ): array {
        return [
            'member_code' => $this->memberCodeService->next($targetLevel, $sponsor),
            'member_member_level_id' => $targetLevel->getKey(),
            'member_parent_member_id' => $sponsor?->getKey() ?? 0,
            'member_name' => $registration->member_registration_name,
            'member_email' => $registration->member_registration_email,
            'member_mobilephone' => $registration->member_registration_mobilephone,
            'member_gender' => $registration->member_registration_gender,
            'member_birth_date' => $registration->member_registration_birth_date,
            'member_identity_type' => $registration->member_registration_identity_type,
            'member_identity_no' => $registration->member_registration_identity_no,
            'member_identity_image' => $registration->member_registration_identity_image,
            'member_identity_image_filename' => '',
            'member_nib' => $registration->member_registration_nib,
            'member_join_datetime' => now(),
            'member_status' => 1,
        ];
    }

    private function createDefaultAddress(
        Member $member,
        MemberRegistration $registration
    ): void {
        MemberAddress::query()->create([
            'member_address_member_id' => $member->getKey(),
            'member_address_label' => 'Domisili',
            'member_address_recipient' => $registration->member_registration_name,
            'member_address_phone' => $registration->member_registration_mobilephone,
            'member_address_full' => $registration->member_registration_address,
            'member_address_province_id' => $registration->member_registration_province_id,
            'member_address_city_id' => $registration->member_registration_city_id,
            'member_address_district_id' => $registration->member_registration_district_id,
            'member_address_subdistrict_id' => $registration->member_registration_subdistrict_id,
            'member_address_country_id' => $registration->member_registration_country_id,
            'member_address_is_default' => 1,
        ]);
    }

    private function createBankAccount(Member $member, MemberRegistration $registration): void
    {
        if ((int) $registration->member_registration_bank_id === 0) {
            return;
        }

        MemberBankAccount::query()->create([
            'member_bank_account_member_id' => $member->getKey(),
            'member_bank_account_bank_id' => $registration->member_registration_bank_id,
            'member_bank_account_name' => $registration->member_registration_bank_account_name,
            'member_bank_account_number' => $registration->member_registration_bank_account_no,
            'member_bank_account_city' => $registration->member_registration_bank_city,
            'member_bank_account_branch' => $registration->member_registration_bank_branch,
            'member_bank_account_is_active' => 1,
            'member_bank_account_is_default' => 1,
        ]);
    }

    private function detailQuery()
    {
        return MemberRegistration::query()->with([
            'level',
            'parent.level',
            'statusAdministrator',
            'province',
            'city',
            'district',
            'subdistrict',
            'country',
            'bank',
        ]);
    }
}
