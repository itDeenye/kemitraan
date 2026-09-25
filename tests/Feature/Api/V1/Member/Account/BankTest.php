<?php

namespace Tests\Feature\Api\V1\Member\Account;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberBankAccount;
use App\Models\MemberGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BankTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_management_requires_authentication(): void
    {
        $this->getJson('/api/v1/member/banks')->assertUnauthorized();
    }

    public function test_member_can_list_their_bank_accounts(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');

        DB::table('ref_bank')->insert(['bank_id' => 1, 'bank_code' => 'BCA', 'bank_name' => 'BCA']);

        MemberBankAccount::query()->create([
            'member_bank_account_member_id' => $account->member_account_member_id,
            'member_bank_account_bank_id' => 1,
            'member_bank_account_name' => 'Budi Bank',
            'member_bank_account_number' => '1234567890',
        ]);

        MemberBankAccount::query()->create([
            'member_bank_account_member_id' => 999, // another member
            'member_bank_account_bank_id' => 1,
            'member_bank_account_name' => 'Andi Bank',
            'member_bank_account_number' => '0987654321',
        ]);

        $this->getJson('/api/v1/member/banks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.account_name', 'Budi Bank')
            ->assertJsonPath('data.0.bank_code', 'BCA')
            ->assertJsonPath('data.0.bank_name', 'BCA')
            ->assertJsonMissingPath('data.0.member_bank_account_name');
    }

    public function test_member_can_create_bank_account(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');

        DB::table('ref_bank')->insert(['bank_id' => 1, 'bank_code' => 'BCA', 'bank_name' => 'BCA']);

        $this->postJson('/api/v1/member/banks', [
            'bank_id' => 1,
            'account_name' => 'Budi',
            'account_number' => '1234567890',
            'city' => 'Surabaya',
            'branch' => 'Tegalsari',
            'is_active' => true,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.account_name', 'Budi')
            ->assertJsonPath('data.account_number', '1234567890')
            ->assertJsonPath('data.city', 'Surabaya')
            ->assertJsonPath('data.branch', 'Tegalsari')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('member_bank_account', [
            'member_bank_account_member_id' => $account->member_account_member_id,
            'member_bank_account_name' => 'Budi',
            'member_bank_account_number' => '1234567890',
            'member_bank_account_city' => 'Surabaya',
            'member_bank_account_branch' => 'Tegalsari',
            'member_bank_account_is_default' => 1,
        ]);
    }

    public function test_member_can_choose_a_default_bank_account(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');
        DB::table('ref_bank')->insert(['bank_id' => 1, 'bank_code' => 'BCA', 'bank_name' => 'BCA']);

        $first = MemberBankAccount::query()->create([
            'member_bank_account_member_id' => $account->member_account_member_id,
            'member_bank_account_bank_id' => 1,
            'member_bank_account_name' => 'Budi Pertama',
            'member_bank_account_number' => '1111111111',
            'member_bank_account_is_default' => 1,
        ]);
        $second = MemberBankAccount::query()->create([
            'member_bank_account_member_id' => $account->member_account_member_id,
            'member_bank_account_bank_id' => 1,
            'member_bank_account_name' => 'Budi Kedua',
            'member_bank_account_number' => '2222222222',
        ]);

        $this->putJson("/api/v1/member/banks/{$second->getKey()}/default")
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('member_bank_account', [
            'member_bank_account_id' => $first->getKey(),
            'member_bank_account_is_default' => 0,
        ]);
        $this->assertDatabaseHas('member_bank_account', [
            'member_bank_account_id' => $second->getKey(),
            'member_bank_account_is_default' => 1,
        ]);
    }

    public function test_deleting_default_bank_promotes_another_active_account(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');
        DB::table('ref_bank')->insert(['bank_id' => 1, 'bank_code' => 'BCA', 'bank_name' => 'BCA']);

        $default = MemberBankAccount::query()->create([
            'member_bank_account_member_id' => $account->member_account_member_id,
            'member_bank_account_bank_id' => 1,
            'member_bank_account_name' => 'Budi Default',
            'member_bank_account_number' => '1111111111',
            'member_bank_account_is_default' => 1,
        ]);
        $replacement = MemberBankAccount::query()->create([
            'member_bank_account_member_id' => $account->member_account_member_id,
            'member_bank_account_bank_id' => 1,
            'member_bank_account_name' => 'Budi Pengganti',
            'member_bank_account_number' => '2222222222',
            'member_bank_account_is_active' => 1,
        ]);

        $this->deleteJson("/api/v1/member/banks/{$default->getKey()}")->assertOk();

        $this->assertDatabaseHas('member_bank_account', [
            'member_bank_account_id' => $replacement->getKey(),
            'member_bank_account_is_default' => 1,
        ]);
    }

    public function test_member_can_view_their_bank_account_detail(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');
        DB::table('ref_bank')->insert(['bank_id' => 1, 'bank_code' => 'BCA', 'bank_name' => 'BCA']);
        $bank = MemberBankAccount::query()->create([
            'member_bank_account_member_id' => $account->member_account_member_id,
            'member_bank_account_bank_id' => 1,
            'member_bank_account_name' => 'Budi Bank',
            'member_bank_account_number' => '1234567890',
        ]);

        $this->getJson("/api/v1/member/banks/{$bank->member_bank_account_id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $bank->member_bank_account_id)
            ->assertJsonPath('data.bank_code', 'BCA')
            ->assertJsonPath('data.bank_name', 'BCA')
            ->assertJsonMissingPath('data.member_bank_account_id');
    }

    public function test_member_cannot_view_other_member_bank_account(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');
        DB::table('ref_bank')->insert(['bank_id' => 1, 'bank_code' => 'BCA', 'bank_name' => 'BCA']);
        $bank = MemberBankAccount::query()->create([
            'member_bank_account_member_id' => 999,
            'member_bank_account_bank_id' => 1,
            'member_bank_account_name' => 'Andi Bank',
            'member_bank_account_number' => '0987654321',
        ]);

        $this->getJson("/api/v1/member/banks/{$bank->member_bank_account_id}")
            ->assertNotFound();
    }

    public function test_member_cannot_delete_other_member_bank_account(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');

        DB::table('ref_bank')->insert(['bank_id' => 1, 'bank_code' => 'BCA', 'bank_name' => 'BCA']);

        $bank = MemberBankAccount::query()->create([
            'member_bank_account_member_id' => 999,
            'member_bank_account_bank_id' => 1,
            'member_bank_account_name' => 'Andi Bank',
            'member_bank_account_number' => '0987654321',
        ]);

        $this->deleteJson("/api/v1/member/banks/{$bank->member_bank_account_id}")->assertStatus(403);
    }

    private function createMemberAccount(): MemberAccount
    {
        $group = MemberGroup::query()->create([
            'member_group_name' => 'Test Group',
            'member_group_is_active' => 1,
        ]);

        $member = Member::query()->create([
            'member_code' => 'MEMBER-TEST',
            'member_member_level_id' => 1,
            'member_name' => 'Test Member',
            'member_email' => 'test@example.test',
            'member_mobilephone' => '08123456789',
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);

        return MemberAccount::query()->create([
            'member_account_member_id' => $member->member_id,
            'member_account_member_group_id' => $group->member_group_id,
            'member_account_username' => 'test.member',
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '',
        ]);
    }
}
