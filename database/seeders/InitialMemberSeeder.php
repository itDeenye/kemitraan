<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberAddress;
use App\Models\MemberBankAccount;
use App\Support\PhoneNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class InitialMemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = config('initial_data.member.password');
        $pin = (string) config('initial_data.member.pin');

        if (blank($password)) {
            $this->command?->warn('Member awal tidak dibuat. Isi DNY_INITIAL_MEMBER_PASSWORD terlebih dahulu.');

            return;
        }

        if (! $this->isStrongPassword($password)) {
            throw new InvalidArgumentException(
                'DNY_INITIAL_MEMBER_PASSWORD minimal 8 karakter dan wajib mengandung huruf besar, huruf kecil, serta angka.',
            );
        }

        if (preg_match('/^[0-9]{6}$/', $pin) !== 1) {
            throw new InvalidArgumentException('DNY_INITIAL_MEMBER_PIN wajib terdiri dari 6 digit angka.');
        }

        $member = Member::query()->find(1) ?? new Member;
        $member->member_id = 1;
        $member->fill([
            'member_code' => '0001/0000/0000',
            'member_member_level_id' => 1,
            'member_name' => config('initial_data.member.name'),
            'member_email' => config('initial_data.member.email'),
            'member_mobilephone' => PhoneNumber::normalize(config('initial_data.member.mobile_phone')),
            'member_status' => 1,
        ]);

        if (! $member->exists) {
            $member->member_join_datetime = now();
        }

        $member->save();

        $account = MemberAccount::query()->find(1) ?? new MemberAccount;
        $account->member_account_id = 1;
        $account->fill([
            'member_account_member_id' => 1,
            'member_account_member_group_id' => 1,
            'member_account_username' => $member->member_code,
        ]);

        if (! $account->exists) {
            $account->member_account_password = Hash::make($password);
            $account->member_account_pin = $pin;
        }

        $account->save();

        $this->seedAddress($member);
        $this->seedBankAccount($member);
    }

    private function seedAddress(Member $member): void
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
        $countryId = (int) DB::table('ref_country')->orderBy('country_id')->value('country_id');

        $address = MemberAddress::query()->find(1) ?? new MemberAddress;
        $address->member_address_id = 1;
        $address->fill([
            'member_address_member_id' => $member->getKey(),
            'member_address_label' => 'Alamat Utama',
            'member_address_recipient' => $member->member_name,
            'member_address_phone' => $member->member_mobilephone,
            'member_address_full' => 'Jalan DNY Nomor 1',
            'member_address_subdistrict_id' => $subdistrictId,
            'member_address_district_id' => $districtId,
            'member_address_city_id' => $cityId,
            'member_address_province_id' => $provinceId,
            'member_address_country_id' => $countryId,
            'member_address_is_default' => 1,
        ])->save();
    }

    private function seedBankAccount(Member $member): void
    {
        $bankId = (int) DB::table('ref_bank')
            ->where('bank_is_active', 1)
            ->orderBy('bank_id')
            ->value('bank_id');

        $bankAccount = MemberBankAccount::query()->find(1) ?? new MemberBankAccount;
        $bankAccount->member_bank_account_id = 1;
        $bankAccount->fill([
            'member_bank_account_member_id' => $member->getKey(),
            'member_bank_account_bank_id' => $bankId,
            'member_bank_account_name' => $member->member_name,
            'member_bank_account_number' => '8800000001',
            'member_bank_account_city' => null,
            'member_bank_account_branch' => null,
            'member_bank_account_is_active' => 1,
            'member_bank_account_is_default' => 1,
        ])->save();
    }

    private function isStrongPassword(string $password): bool
    {
        return mb_strlen($password) >= 8
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1;
    }
}
