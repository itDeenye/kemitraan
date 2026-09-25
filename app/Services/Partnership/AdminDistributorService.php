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
use App\Services\Notification\PartnershipEmailService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminDistributorService
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
    public function list(array $params): array
    {
        $defaultAddresses = DB::table('member_address')
            ->where('member_address_is_default', 1);

        return DataTable::select([
            'member.member_id as id',
            'member.member_code as code',
            'member.member_name as name',
            'member.member_email as email',
            'member.member_mobilephone as mobile_phone',
            'member.member_status as status',
            'member.member_join_datetime as joined_at',
            'ref_province.province_name as province_name',
            'ref_city.city_name as city_name',
        ])
            ->from('member')
            ->leftJoin('member_level', 'member_level.member_level_id = member.member_member_level_id')
            ->leftJoinSub($defaultAddresses, 'default_address', 'default_address.member_address_member_id = member.member_id')
            ->leftJoin('ref_province', 'ref_province.province_id = default_address.member_address_province_id')
            ->leftJoin('ref_city', 'ref_city.city_id = default_address.member_address_city_id')
            ->where('member.member_status', '!=', 3)
            ->where('member_level.member_level_code', 'DST')
            ->search(['member.member_code', 'member.member_name', 'member.member_mobilephone', 'ref_city.city_name', 'ref_province.province_name'])
            ->defaultSort('-id')
            ->get($params);
    }

    public function detail(Member $member): Member
    {
        if ($member->level?->member_level_code !== 'DST') {
            throw new ProcessException('Mitra yang dipilih bukan Distributor.');
        }

        return Member::query()
            ->with(['accounts', 'level', 'addresses', 'bankAccounts'])
            ->where('member_status', '!=', 3)
            ->findOrFail($member->getKey());
    }

    /** @param array<string, mixed> $data */
    public function store(array $data): Member
    {
        /** @var array{member: Member, username: string, password: string} $result */
        $result = DB::transaction(function () use ($data): array {
            $distributorLevel = MemberLevel::query()
                ->where('member_level_code', 'DST')
                ->where('member_level_is_active', 1)
                ->first();

            if (! $distributorLevel) {
                throw new ProcessException('Tingkat Distributor aktif belum tersedia.');
            }

            $group = MemberGroup::query()
                ->where('member_group_name', $distributorLevel->member_level_name)
                ->where('member_group_is_active', 1)
                ->first();

            if (! $group) {
                throw new ProcessException('Grup akses untuk tingkat mitra belum tersedia atau tidak aktif.');
            }

            $memberCode = $this->memberCodeService->next($distributorLevel);
            $username = $memberCode; // Generate username from member code
            $plainPassword = $this->memberPasswordService->approvalPassword($data['birth_date']);

            $member = Member::query()->create([
                'member_code' => $memberCode,
                'member_member_level_id' => $distributorLevel->getKey(),
                'member_parent_member_id' => 0,
                'member_name' => $data['name'],
                'member_email' => $data['email'] ?? '',
                'member_mobilephone' => $data['mobile_phone'],
                'member_gender' => $data['gender'],
                'member_birth_date' => $data['birth_date'],
                'member_identity_type' => $data['identity_type'],
                'member_identity_no' => $data['identity_no'],
                'member_identity_image' => $data['identity_image_url'] ?? '',
                'member_identity_image_filename' => '',
                'member_join_datetime' => now(),
                'member_status' => $data['status'],
                'member_instagram' => $data['instagram'] ?? null,
                'member_facebook' => $data['facebook'] ?? null,
                'member_tiktok' => $data['tiktok'] ?? null,
            ]);

            MemberAccount::query()->create([
                'member_account_member_id' => $member->getKey(),
                'member_account_member_group_id' => $group->getKey(),
                'member_account_username' => $username,
                'member_account_password' => Hash::make($plainPassword),
                'member_account_pin' => '',
            ]);

            MemberAddress::query()->create([
                'member_address_member_id' => $member->getKey(),
                'member_address_label' => 'KTP',
                'member_address_recipient' => $data['name'],
                'member_address_phone' => $data['mobile_phone'],
                'member_address_full' => $data['address'],
                'member_address_province_id' => $data['province_id'],
                'member_address_city_id' => $data['city_id'],
                'member_address_district_id' => $data['district_id'],
                'member_address_subdistrict_id' => $data['subdistrict_id'],
                'member_address_country_id' => $data['country_id'] ?? 1,
                'member_address_is_default' => 0,
            ]);

            MemberAddress::query()->create([
                'member_address_member_id' => $member->getKey(),
                'member_address_label' => 'Domisili',
                'member_address_recipient' => $data['name'],
                'member_address_phone' => $data['mobile_phone'],
                'member_address_full' => $data['domicile_address'],
                'member_address_province_id' => $data['province_id'],
                'member_address_city_id' => $data['city_id'],
                'member_address_district_id' => $data['district_id'],
                'member_address_subdistrict_id' => $data['subdistrict_id'],
                'member_address_country_id' => $data['country_id'] ?? 1,
                'member_address_is_default' => 1,
            ]);

            if ((int) ($data['bank_id'] ?? 0) !== 0) {
                MemberBankAccount::query()->create([
                    'member_bank_account_member_id' => $member->getKey(),
                    'member_bank_account_bank_id' => $data['bank_id'],
                    'member_bank_account_name' => $data['bank_account_name'],
                    'member_bank_account_number' => $data['bank_account_number'],
                    'member_bank_account_city' => $data['bank_city'] ?? null,
                    'member_bank_account_branch' => $data['bank_branch'] ?? null,
                    'member_bank_account_is_active' => 1,
                    'member_bank_account_is_default' => 1,
                ]);
            }

            MemberHistory::query()->create([
                'member_history_member_id' => $member->getKey(),
                'member_history_action' => 'register_by_admin',
                'member_history_from_level_id' => 0,
                'member_history_to_level_id' => $distributorLevel->getKey(),
                'member_history_upline_member_id' => 0,
                'member_history_upline_member_level_id' => 0,
                'member_history_downline_member_id' => 0,
                'member_history_reason' => 'Distributor ditambahkan secara manual oleh Admin.',
                'member_history_approved_by' => auth()->id() ?? 0,
                'member_history_datetime' => now(),
            ]);

            return [
                'member' => $this->detail($member),
                'username' => $username,
                'password' => $plainPassword,
            ];
        });
        $this->partnershipEmailService->sendMemberCredentials(
            $result['member'],
            $result['username'],
            $result['password'],
        );

        return $result['member'];
    }
}
