<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberAddress;
use App\Models\MemberBankAccount;
use App\Models\MemberLevel;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\InitialAdministratorSeeder;
use Database\Seeders\InitialCompanyBankSeeder;
use Database\Seeders\InitialMemberSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InitialDataSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_initial_administrator_has_deterministic_id_and_password_is_not_reset(): void
    {
        config()->set('initial_data.administrator.password', 'Initial123');
        $this->seed([AccessControlSeeder::class, InitialAdministratorSeeder::class]);

        $administrator = SiteAdministrator::query()->findOrFail(1);

        $this->assertSame(1, SiteAdministratorGroup::query()->findOrFail(1)->administrator_group_id);
        $this->assertSame('admin', $administrator->administrator_username);
        $this->assertTrue(Hash::check('Initial123', $administrator->administrator_password));

        config()->set('initial_data.administrator.password', 'Changed123');
        $this->seed(InitialAdministratorSeeder::class);
        $administrator->refresh();

        $this->assertTrue(Hash::check('Initial123', $administrator->administrator_password));
        $this->assertFalse(Hash::check('Changed123', $administrator->administrator_password));
    }

    public function test_initial_member_and_account_have_deterministic_ids(): void
    {
        config()->set('initial_data.member.password', 'Member123');
        config()->set('initial_data.member.pin', '123456');
        $this->seed([AccessControlSeeder::class, InitialMemberSeeder::class]);

        $member = Member::query()->findOrFail(1);
        $account = MemberAccount::query()->findOrFail(1);
        $address = MemberAddress::query()->findOrFail(1);
        $bankAccount = MemberBankAccount::query()->findOrFail(1);

        $this->assertSame('0001/0000/0000', $member->member_code);
        $this->assertSame(1, $member->member_member_level_id);
        $this->assertSame('DST', MemberLevel::query()->findOrFail(1)->member_level_code);
        $this->assertSame(5_000, MemberLevel::query()->findOrFail(1)->member_level_point_value);
        $this->assertSame(4_000, MemberLevel::query()->findOrFail(2)->member_level_point_value);
        $this->assertSame(3_000, MemberLevel::query()->findOrFail(3)->member_level_point_value);
        $this->assertSame(1, $account->member_account_member_id);
        $this->assertSame(1, $account->member_account_member_group_id);
        $this->assertSame('0001/0000/0000', $account->member_account_username);
        $this->assertTrue(Hash::check('Member123', $account->member_account_password));
        $this->assertSame($member->getKey(), $address->member_address_member_id);
        $this->assertSame('Alamat Utama', $address->member_address_label);
        $this->assertTrue($address->member_address_is_default);
        $this->assertSame($member->getKey(), $bankAccount->member_bank_account_member_id);
        $this->assertTrue($bankAccount->member_bank_account_is_active);
        $this->assertTrue($bankAccount->member_bank_account_is_default);
    }

    public function test_initial_company_bank_accounts_have_deterministic_ids(): void
    {
        $this->seed(InitialCompanyBankSeeder::class);

        $this->assertDatabaseHas('bank_company', [
            'bank_company_id' => 1,
            'bank_company_type' => 'company',
            'bank_company_bank_acc_name' => 'PT Deenye Berkah Abadi',
            'bank_company_bank_acc_number' => '880000000001',
            'bank_company_bank_is_active' => 1,
        ]);
        $this->assertDatabaseHas('bank_company', [
            'bank_company_id' => 2,
            'bank_company_type' => 'spread_payment',
            'bank_company_bank_acc_name' => 'DNY Spread Payment',
            'bank_company_bank_acc_number' => '880000000002',
            'bank_company_bank_is_active' => 1,
        ]);
        $this->assertSame(2, DB::table('bank_company')->count());
    }
}
