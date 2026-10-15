<?php

namespace App\Services\Partnership;

use App\Exceptions\ProcessException;
use App\Jobs\SyncSupplyChainMember;
use App\Libraries\DataTable;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\MemberHistory;
use App\Models\MemberLevel;
use App\Models\MemberNetworkSwitch;
use App\Models\MemberRegistration;
use App\Models\RefBank;
use App\Models\SiteAdministrator;
use App\Services\Notification\PartnershipEmailService;
use App\Support\MediaUrl;
use App\Support\MemberIdentity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MemberRegistrationService
{
    public function __construct(
        private readonly MemberCodeService $memberCodeService,
        private readonly MemberPasswordService $memberPasswordService,
        private readonly PartnershipEmailService $partnershipEmailService,
        private readonly MemberOnboardingContacts $onboardingContacts,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function memberRegistrations(array $params, int $sponsorId): array
    {
        return $this->registrationList(
            $params,
            $this->accessibleRegistrationSponsorIds($sponsorId),
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function adminRegistrations(array $params): array
    {
        return $this->registrationList($params, adminOnly: true);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function distributorRegistrations(array $params, int $distributorId): array
    {
        return $this->registrationList($params, distributorId: $distributorId);
    }

    public function distributorRegistration(MemberRegistration $registration, int $distributorId): MemberRegistration
    {
        return $this->detailQuery()
            ->where('member_registration_distributor_member_id', $distributorId)
            ->findOrFail($registration->getKey());
    }

    public function memberOptions(Member $sponsor): array
    {
        $targetLevels = $this->targetLevelsForSponsor($sponsor);
        $targetLevel = $targetLevels->firstOrFail();
        $agents = $this->availableAgentsForDistributor($sponsor);
        $sponsorData = [
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
            'levels' => $targetLevels->map(fn (MemberLevel $level): array => [
                'id' => (int) $level->getKey(),
                'code' => $level->member_level_code,
                'name' => $level->member_level_name,
            ])->values()->all(),
            'sponsor' => $sponsorData,
            'agents' => $agents,
            'genders' => ['Laki-laki', 'Perempuan'],
            'identity_types' => MemberIdentity::TYPES,
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
            ->whereIn(
                'member_registration_upline_member_id',
                $this->accessibleRegistrationSponsorIds($sponsorId),
            )
            ->firstOrFail();
    }

    public function adminRegistration(MemberRegistration $registration): MemberRegistration
    {
        return $this->detailQuery()->readyForAdmin()->findOrFail($registration->getKey());
    }

    public function approveByDistributor(MemberRegistration $registration, MemberAccount $account, ?string $note): MemberRegistration
    {
        return $this->reviewByDistributor($registration, $account, 'approved', $note);
    }

    public function rejectByDistributor(MemberRegistration $registration, MemberAccount $account, string $note): MemberRegistration
    {
        return $this->reviewByDistributor($registration, $account, 'rejected', $note);
    }

    private function reviewByDistributor(MemberRegistration $registration, MemberAccount $account, string $decision, ?string $note): MemberRegistration
    {
        return DB::transaction(function () use ($registration, $account, $decision, $note): MemberRegistration {
            $distributorId = (int) $account->member_account_member_id;
            $lockedRegistration = MemberRegistration::query()
                ->whereKey($registration->getKey())
                ->where('member_registration_distributor_member_id', $distributorId)
                ->lockForUpdate()->firstOrFail();
            $this->ensureRequested($lockedRegistration);

            if ($lockedRegistration->member_registration_distributor_approval_status !== 'pending') {
                throw new ProcessException('Persetujuan Distributor sudah diproses.');
            }

            $distributor = Member::query()->with('level')->whereKey($distributorId)->lockForUpdate()->firstOrFail();
            if ($distributor->level?->member_level_code !== 'DST') {
                throw new ProcessException('Hanya Distributor yang dapat memproses persetujuan ini.', 403);
            }
            $this->ensureMemberCanRecruit($distributor);

            $agent = Member::query()->with('level')
                ->whereKey($lockedRegistration->member_registration_upline_member_id)
                ->lockForUpdate()->firstOrFail();
            if ($agent->level?->member_level_code !== 'AGT'
                || (int) $agent->member_parent_member_id !== $distributorId) {
                throw new ProcessException('Agen Utama bukan bagian dari jaringan Distributor.');
            }
            $targetLevel = MemberLevel::query()->whereKey($lockedRegistration->member_registration_member_level_id)
                ->where('member_level_code', 'RSL')->where('member_level_is_active', 1)->firstOrFail();
            $this->ensureSponsorCanRecruit($agent, $targetLevel);

            $lockedRegistration->update([
                'member_registration_distributor_approval_status' => $decision,
                'member_registration_distributor_approval_note' => $note,
                'member_registration_distributor_approval_datetime' => now(),
                ...($decision === 'rejected' ? [
                    'member_registration_status' => 'rejected',
                    'member_registration_note' => $note,
                ] : []),
            ]);

            return $this->distributorRegistration($lockedRegistration, $distributorId);
        });
    }

    /** @param array<string, mixed> $data */
    public function createByMember(MemberAccount $account, array $data): MemberRegistration
    {
        $submitter = Member::query()
            ->with('level')
            ->whereKey($account->member_account_member_id)
            ->where('member_status', 1)
            ->firstOrFail();
        $targetLevel = MemberLevel::query()
            ->whereKey((int) $data['level_id'])
            ->where('member_level_is_active', 1)
            ->firstOrFail();

        $this->ensureSubmitterCanRegister($submitter, $targetLevel);
        $sponsor = $this->registrationSponsorForSubmitter(
            $submitter,
            $targetLevel,
            ! empty($data['upline_member_id']) ? (int) $data['upline_member_id'] : null,
        );
        $this->ensureSponsorCanRecruit($sponsor, $targetLevel);

        return $this->createRegistration($data, $targetLevel, $sponsor, $submitter);
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

            $this->ensureReadyForAdmin($lockedRegistration);

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
            $this->onboardingContacts->store(
                $member,
                $this->legacyAddresses($lockedRegistration),
                $lockedRegistration->member_registration_bank_accounts ?? $this->legacyBankAccounts($lockedRegistration),
            );
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
        if (config('services.supply_chain.enabled')) {
            SyncSupplyChainMember::dispatch((int) $result['member']->getKey());
        }
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
            $this->ensureReadyForAdmin($lockedRegistration);
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
    private function registrationList(array $params, ?array $sponsorIds = null, bool $adminOnly = false, ?int $distributorId = null): array
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
            "{$registrationTable}.member_registration_identity_type as identity_type",
            "{$registrationTable}.member_registration_identity_image as identity_image_url",
            "{$registrationTable}.member_registration_nib as nib",
            "{$registrationTable}.member_registration_npwp as npwp",
            "{$registrationTable}.member_registration_image as image_url",
            "{$registrationTable}.member_registration_payment_proof_image as payment_proof_image_url",
            "{$registrationTable}.member_registration_status as status",
            "{$registrationTable}.member_registration_status_administrator_id as processed_by_id",
            "{$administratorTable}.administrator_name as processed_by_name",
            "{$registrationTable}.member_registration_datetime as submitted_at",
            "{$registrationTable}.member_registration_status_datetime as processed_at",
            "{$registrationTable}.member_registration_distributor_member_id as approval_distributor_id",
            'approval_distributor.member_code as approval_distributor_code',
            'approval_distributor.member_name as approval_distributor_name',
            "{$registrationTable}.member_registration_distributor_approval_status as distributor_approval_status",
            "{$registrationTable}.member_registration_distributor_approval_note as distributor_approval_note",
            "{$registrationTable}.member_registration_distributor_approval_datetime as distributor_approval_processed_at",
        ])
            ->from($registrationTable)
            ->leftJoin($levelTable, "{$levelTable}.member_level_id = {$registrationTable}.member_registration_member_level_id")
            ->leftJoin($memberTable, "{$memberTable}.member_id = {$registrationTable}.member_registration_upline_member_id")
            ->leftJoin("{$memberTable} as approval_distributor", "approval_distributor.member_id = {$registrationTable}.member_registration_distributor_member_id")
            ->leftJoin(
                $administratorTable,
                "{$administratorTable}.administrator_id = {$registrationTable}.member_registration_status_administrator_id"
            );

        if ($sponsorIds !== null) {
            $query->whereIn(
                "{$registrationTable}.member_registration_upline_member_id",
                $sponsorIds,
            );
        }

        if ($adminOnly) {
            $query->whereIn("{$registrationTable}.member_registration_distributor_approval_status", MemberRegistration::ADMIN_READY_APPROVAL_STATUSES);
        }
        if ($distributorId !== null) {
            $query->where("{$registrationTable}.member_registration_distributor_member_id", $distributorId);
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
            'identity_types' => MemberIdentity::TYPES,
        ];
    }

    /** @param array<string, mixed> $data */
    private function createRegistration(
        array $data,
        MemberLevel $targetLevel,
        ?Member $sponsor = null,
        ?Member $submitter = null,
    ): MemberRegistration {
        $approvalDistributor = null;
        if ($submitter?->level?->member_level_code === 'AGT' && $targetLevel->member_level_code === 'RSL') {
            $approvalDistributor = Member::query()->with('level')
                ->whereKey($submitter->member_parent_member_id)
                ->where('member_status', 1)
                ->whereHas('level', fn ($query) => $query->where('member_level_code', 'DST')->where('member_level_is_active', 1))
                ->first();
            if ($approvalDistributor === null) {
                throw new ProcessException('Distributor Agen Utama tidak aktif atau tidak tersedia.');
            }
            $this->ensureMemberCanRecruit($approvalDistributor);
        }
        $banks = $this->onboardingContacts->bankAccounts($data);
        $bank = collect($banks)->firstWhere('is_default', true);
        $bankName = $bank !== null
            ? (string) RefBank::query()->where('bank_id', $bank['bank_id'])->value('bank_name')
            : null;
        $registration = MemberRegistration::query()->create([
            'member_registration_member_level_id' => $targetLevel->getKey(),
            'member_registration_upline_member_id' => $sponsor?->getKey() ?? 0,
            'member_registration_submitter_member_id' => $submitter?->getKey() ?? 0,
            'member_registration_distributor_member_id' => $approvalDistributor?->getKey() ?? 0,
            'member_registration_distributor_approval_status' => $approvalDistributor === null ? 'not_required' : 'pending',
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
            'member_registration_bank_accounts' => $banks,
            'member_registration_bank_id' => $bank['bank_id'] ?? 0,
            'member_registration_bank_name' => $bankName,
            'member_registration_bank_account_name' => $bank['account_name'] ?? null,
            'member_registration_bank_account_no' => $bank['account_number'] ?? null,
            'member_registration_bank_city' => $bank['city'] ?? null,
            'member_registration_bank_branch' => $bank['branch'] ?? null,
            'member_registration_identity_type' => $data['identity_type'],
            'member_registration_identity_no' => $data['identity_no'],
            'member_registration_identity_image' => MediaUrl::canonicalPrivateUrl($data['identity_image_url'] ?? null),
            'member_registration_identity_image_filename' => '',
            'member_registration_nib' => $data['nib'] ?? null,
            'member_registration_npwp' => $data['npwp'] ?? null,
            'member_registration_image' => $data['image_url'] ?? null,
            'member_registration_payment_proof_image' => MediaUrl::canonicalPrivateUrl($data['payment_proof_image_url'] ?? null),
            'member_registration_username' => '',
            // A temporary hash keeps legacy schema compatibility. The real
            // credential is generated only when an administrator approves.
            'member_registration_password' => Hash::make((string) random_int(10000000, 99999999)),
            'member_registration_status' => 'requested',
            'member_registration_status_administrator_id' => 0,
            'member_registration_note' => null,
            'member_registration_datetime' => now(),
        ]);

        if ($approvalDistributor !== null && $submitter !== null) {
            $this->partnershipEmailService->sendDistributorRegistrationReview($registration, $approvalDistributor, $submitter);
        }

        return $this->detailQuery()->findOrFail($registration->getKey());
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

        if ($registration->member_registration_distributor_approval_status === 'approved'
            && (int) $sponsor->member_parent_member_id !== (int) $registration->member_registration_distributor_member_id) {
            throw new ProcessException('Jaringan Agen Utama berubah. Ajukan pendaftaran kembali untuk persetujuan Distributor.');
        }

        return $sponsor;
    }

    private function ensureSponsorCanRecruit(Member $sponsor, MemberLevel $targetLevel): void
    {
        $this->ensureMemberCanRecruit($sponsor);

        if (! in_array($targetLevel->member_level_code, $this->allowedTargetLevelCodes($sponsor), true)) {
            throw new ProcessException('Sponsor tidak memiliki hak untuk mendaftarkan tingkat mitra tersebut.');
        }
    }

    /** @return array<int, string> */
    private function allowedTargetLevelCodes(Member $sponsor): array
    {
        return match ($sponsor->level?->member_level_code) {
            'DST' => ['AGT', 'RSL'],
            'AGT' => ['RSL'],
            default => [],
        };
    }

    /** @return Collection<int, MemberLevel> */
    private function targetLevelsForSponsor(Member $sponsor): Collection
    {
        $targetCodes = $this->submissionTargetLevelCodes($sponsor);

        if ($targetCodes === []) {
            throw new ProcessException('Hanya Distributor dan Agen Utama yang dapat mendaftarkan mitra.', 403);
        }

        $targetLevels = MemberLevel::query()
            ->whereIn('member_level_code', $targetCodes)
            ->where('member_level_is_active', 1)
            ->orderBy('member_level_sort_order')
            ->get();

        if ($targetLevels->count() !== count($targetCodes)) {
            throw new ProcessException('Tingkat mitra tujuan yang aktif belum tersedia.');
        }

        return $targetLevels;
    }

    private function ensureSubmitterCanRegister(Member $submitter, MemberLevel $targetLevel): void
    {
        $this->ensureMemberCanRecruit($submitter);

        if (! in_array($targetLevel->member_level_code, $this->submissionTargetLevelCodes($submitter), true)) {
            throw new ProcessException('Mitra tidak memiliki hak untuk mendaftarkan tingkat mitra tersebut.');
        }
    }

    private function ensureMemberCanRecruit(Member $member): void
    {
        if ($member->member_status !== 1 || ! $member->level?->member_level_is_active) {
            throw new ProcessException('Sponsor tidak aktif atau tidak tersedia.');
        }

        $hasPendingDowngrade = MemberNetworkSwitch::query()
            ->where('network_switch_member_id', $member->getKey())
            ->where('network_switch_type', 'downgrade')
            ->whereIn('network_switch_status', ['scheduled', 'approved'])
            ->whereNull('network_switch_applied_datetime')
            ->exists();

        if ($hasPendingDowngrade) {
            throw new ProcessException('Mitra yang sedang dijadwalkan turun tingkat tidak dapat menerima pendaftaran mitra baru.');
        }
    }

    /** @return array<int, string> */
    private function submissionTargetLevelCodes(Member $submitter): array
    {
        return match ($submitter->level?->member_level_code) {
            'DST' => ['AGT', 'RSL'],
            'AGT' => ['RSL'],
            default => [],
        };
    }

    private function registrationSponsorForSubmitter(
        Member $submitter,
        MemberLevel $targetLevel,
        ?int $uplineMemberId,
    ): Member {
        if ($submitter->level?->member_level_code !== 'DST'
            || $targetLevel->member_level_code !== 'RSL') {
            if ($uplineMemberId !== null) {
                throw new ProcessException('Agen Utama hanya dipilih saat Distributor mendaftarkan Reseller.');
            }

            return $submitter;
        }

        if (! $uplineMemberId) {
            return $submitter;
        }

        $agent = Member::query()
            ->with('level')
            ->whereKey($uplineMemberId)
            ->where('member_parent_member_id', $submitter->getKey())
            ->where('member_status', 1)
            ->whereHas('level', fn ($query) => $query
                ->where('member_level_code', 'AGT')
                ->where('member_level_is_active', 1))
            ->first();

        if (! $agent) {
            throw new ProcessException('Agen Utama tidak aktif atau bukan bagian dari jaringan Distributor.');
        }

        return $agent;
    }

    /** @return array<int, array{id: int, code: string, name: string, level_code: string}> */
    private function availableAgentsForDistributor(Member $distributor): array
    {
        if ($distributor->level?->member_level_code !== 'DST') {
            return [];
        }

        return Member::query()
            ->select(['member_id', 'member_code', 'member_name'])
            ->where('member_parent_member_id', $distributor->getKey())
            ->where('member_status', 1)
            ->whereHas('level', fn ($query) => $query
                ->where('member_level_code', 'AGT')
                ->where('member_level_is_active', 1))
            ->orderBy('member_name')
            ->orderBy('member_code')
            ->get()
            ->map(fn (Member $agent): array => [
                'id' => (int) $agent->getKey(),
                'code' => $agent->member_code,
                'name' => $agent->member_name,
                'level_code' => 'AGT',
            ])
            ->all();
    }

    /** @return array<int, int> */
    private function accessibleRegistrationSponsorIds(int $memberId): array
    {
        $member = Member::query()
            ->with('level')
            ->whereKey($memberId)
            ->where('member_status', 1)
            ->firstOrFail();
        $sponsorIds = [(int) $member->getKey()];

        if ($member->level?->member_level_code !== 'DST') {
            return $sponsorIds;
        }

        return [
            ...$sponsorIds,
            ...Member::query()
                ->where('member_parent_member_id', $member->getKey())
                ->where('member_status', 1)
                ->whereHas('level', fn ($query) => $query->where('member_level_code', 'AGT'))
                ->pluck('member_id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
        ];
    }

    private function ensureRequested(MemberRegistration $registration): void
    {
        if ($registration->member_registration_status !== 'requested') {
            throw new ProcessException('Pengajuan sudah diproses dan tidak dapat diproses kembali.');
        }
    }

    private function ensureReadyForAdmin(MemberRegistration $registration): void
    {
        if (! in_array($registration->member_registration_distributor_approval_status, MemberRegistration::ADMIN_READY_APPROVAL_STATUSES, true)) {
            throw new ProcessException('Pengajuan harus disetujui Distributor terlebih dahulu.');
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
            'member_npwp' => $registration->member_registration_npwp,
            'member_image' => $registration->member_registration_image,
            'member_join_datetime' => now(),
            'member_status' => 1,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function legacyAddresses(MemberRegistration $registration): array
    {
        return [[
            'label' => 'Domisili',
            'recipient' => $registration->member_registration_name,
            'phone' => $registration->member_registration_mobilephone,
            'full_address' => $registration->member_registration_address,
            'province_id' => $registration->member_registration_province_id,
            'city_id' => $registration->member_registration_city_id,
            'district_id' => $registration->member_registration_district_id,
            'subdistrict_id' => $registration->member_registration_subdistrict_id,
            'country_id' => $registration->member_registration_country_id,
            'is_default' => true,
        ]];
    }

    /** @return array<int, array<string, mixed>> */
    private function legacyBankAccounts(MemberRegistration $registration): array
    {
        if ((int) $registration->member_registration_bank_id === 0) {
            return [];
        }

        return [[
            'bank_id' => $registration->member_registration_bank_id,
            'account_name' => $registration->member_registration_bank_account_name,
            'account_number' => $registration->member_registration_bank_account_no,
            'city' => $registration->member_registration_bank_city,
            'branch' => $registration->member_registration_bank_branch,
            'is_active' => true,
            'is_default' => true,
        ]];
    }

    private function detailQuery()
    {
        return MemberRegistration::query()->with([
            'level',
            'parent.level',
            'parent.parent.level',
            'submittedBy.level',
            'statusAdministrator',
            'approvalDistributor',
            'province',
            'city',
            'district',
            'subdistrict',
            'country',
            'bank',
        ]);
    }
}
