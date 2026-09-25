<?php

namespace Tests\Feature\Api\V1\Member\Account;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberAddress;
use App\Models\MemberGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_address_management_requires_authentication(): void
    {
        $this->getJson('/api/v1/member/addresses')->assertUnauthorized();
    }

    public function test_member_can_list_their_addresses(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');
        $this->createRegionReferences();

        MemberAddress::query()->create([
            'member_address_member_id' => $account->member_account_member_id,
            'member_address_label' => 'Rumah',
            'member_address_recipient' => 'Budi',
            'member_address_phone' => '08123',
            'member_address_full' => 'Jalan A',
            'member_address_province_id' => 11,
            'member_address_city_id' => 1101,
            'member_address_district_id' => 110101,
            'member_address_subdistrict_id' => 11010101,
            'member_address_country_id' => 1,
            'member_address_is_default' => 1,
        ]);

        MemberAddress::query()->create([
            'member_address_member_id' => 999, // another member
            'member_address_recipient' => 'Andi',
            'member_address_phone' => '08111',
            'member_address_full' => 'Jalan B',
        ]);

        $this->getJson('/api/v1/member/addresses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Rumah')
            ->assertJsonPath('data.0.region.province_name', 'Aceh')
            ->assertJsonPath('data.0.region.city_name', 'Kabupaten Simeulue')
            ->assertJsonPath('data.0.region.district_name', 'Teupah Selatan')
            ->assertJsonPath('data.0.region.subdistrict_name', 'Latiung')
            ->assertJsonPath('data.0.region.postal_code', 23891);
    }

    public function test_member_can_create_address_and_set_as_default_automatically_if_first(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');
        $this->createRegionReferences();

        $this->postJson('/api/v1/member/addresses', [
            'label' => 'Kantor',
            'recipient' => 'Budi',
            'phone' => '08123',
            'full_address' => 'Jalan Kantor',
            'province_id' => '11',
            'city_id' => '1101',
            'district_id' => '110101',
            'subdistrict_id' => 11010101,
            'country_id' => 1,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.region.province_id', '11')
            ->assertJsonPath('data.region.subdistrict_id', 11010101);

        $this->assertDatabaseHas('member_address', [
            'member_address_member_id' => $account->member_account_member_id,
            'member_address_label' => 'Kantor',
            'member_address_is_default' => 1,
        ]);
    }

    public function test_member_can_view_their_address_detail(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');
        $this->createRegionReferences();
        $address = MemberAddress::query()->create([
            'member_address_member_id' => $account->member_account_member_id,
            'member_address_label' => 'Rumah',
            'member_address_recipient' => 'Budi',
            'member_address_phone' => '08123',
            'member_address_full' => 'Jalan A',
            'member_address_province_id' => 11,
            'member_address_city_id' => 1101,
            'member_address_district_id' => 110101,
            'member_address_subdistrict_id' => 11010101,
            'member_address_country_id' => 1,
        ]);

        $this->getJson("/api/v1/member/addresses/{$address->member_address_id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $address->member_address_id)
            ->assertJsonPath('data.region.country_name', 'Indonesia');
    }

    public function test_member_cannot_view_other_member_address(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');
        $address = MemberAddress::query()->create([
            'member_address_member_id' => 999,
            'member_address_recipient' => 'Andi',
            'member_address_phone' => '08123',
            'member_address_full' => 'Jalan B',
        ]);

        $this->getJson("/api/v1/member/addresses/{$address->member_address_id}")
            ->assertNotFound();
    }

    public function test_member_can_update_address(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');

        $address = MemberAddress::query()->create([
            'member_address_member_id' => $account->member_account_member_id,
            'member_address_recipient' => 'Budi',
            'member_address_phone' => '08123',
            'member_address_full' => 'Jalan A',
        ]);

        $this->putJson("/api/v1/member/addresses/{$address->member_address_id}", [
            'recipient' => 'Budi Update',
        ])
            ->assertOk()
            ->assertJsonPath('data.recipient', 'Budi Update');
    }

    public function test_updating_address_does_not_remove_its_default_status(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');

        $address = MemberAddress::query()->create([
            'member_address_member_id' => $account->member_account_member_id,
            'member_address_recipient' => 'Budi',
            'member_address_phone' => '08123',
            'member_address_full' => 'Jalan A',
            'member_address_is_default' => 1,
        ]);

        $this->putJson("/api/v1/member/addresses/{$address->member_address_id}", [
            'recipient' => 'Budi Update',
            'is_default' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('member_address', [
            'member_address_id' => $address->member_address_id,
            'member_address_is_default' => 1,
        ]);
    }

    public function test_member_can_choose_a_default_address(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');

        $first = MemberAddress::query()->create([
            'member_address_member_id' => $account->member_account_member_id,
            'member_address_recipient' => 'Budi Pertama',
            'member_address_phone' => '08123',
            'member_address_full' => 'Jalan Pertama',
            'member_address_is_default' => 1,
        ]);
        $second = MemberAddress::query()->create([
            'member_address_member_id' => $account->member_account_member_id,
            'member_address_recipient' => 'Budi Kedua',
            'member_address_phone' => '08124',
            'member_address_full' => 'Jalan Kedua',
        ]);

        $this->putJson("/api/v1/member/addresses/{$second->getKey()}/default")
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('member_address', [
            'member_address_id' => $first->getKey(),
            'member_address_is_default' => 0,
        ]);
        $this->assertDatabaseHas('member_address', [
            'member_address_id' => $second->getKey(),
            'member_address_is_default' => 1,
        ]);
    }

    public function test_member_cannot_update_other_member_address(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');

        $address = MemberAddress::query()->create([
            'member_address_member_id' => 999,
            'member_address_recipient' => 'Andi',
            'member_address_phone' => '08123',
            'member_address_full' => 'Jalan A',
        ]);

        $this->putJson("/api/v1/member/addresses/{$address->member_address_id}", [
            'recipient' => 'Hacked',
        ])->assertStatus(403);
    }

    public function test_address_region_must_follow_reference_hierarchy(): void
    {
        $account = $this->createMemberAccount();
        $this->actingAs($account, 'member_api');
        $this->createRegionReferences();

        $this->postJson('/api/v1/member/addresses', [
            'recipient' => 'Budi',
            'phone' => '08123',
            'full_address' => 'Jalan Kantor',
            'province_id' => '12',
            'city_id' => '1101',
            'district_id' => '110101',
            'subdistrict_id' => 11010101,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonPath(
                'errors.province_id.0',
                'Wilayah provinsi, kota, kecamatan, dan kelurahan tidak saling sesuai.'
            );
    }

    private function createRegionReferences(): void
    {
        DB::table('ref_country')->insert([
            'country_id' => 1,
            'country_iso_code' => 'ID',
            'country_phone_code' => '+62',
            'country_name' => 'Indonesia',
        ]);
        DB::table('ref_province')->insert([
            ['province_id' => '11', 'province_name' => 'Aceh'],
            ['province_id' => '12', 'province_name' => 'Sumatera Utara'],
        ]);
        DB::table('ref_city')->insert([
            'city_id' => '1101',
            'city_province_id' => '11',
            'city_name' => 'Kabupaten Simeulue',
            'city_type' => 'Kabupaten',
        ]);
        DB::table('ref_district')->insert([
            'district_id' => '110101',
            'district_city_id' => '1101',
            'district_name' => 'Teupah Selatan',
        ]);
        DB::table('ref_subdistrict')->insert([
            'subdistrict_id' => 11010101,
            'subdistrict_district_id' => 110101,
            'subdistrict_name' => 'Latiung',
            'subdistrict_zip_code' => 23891,
        ]);
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
