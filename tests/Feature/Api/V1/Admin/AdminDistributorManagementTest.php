<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Mail\MemberCredentialsMail;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberLevel;
use App\Models\RefCity;
use App\Models\RefDistrict;
use App\Models\RefProvince;
use App\Models\RefSubdistrict;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminDistributorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_list_and_view_distributors(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $this->createDistributor('DST-001', 'Ani Distributor', 1);
        $this->createDistributor('DST-002', 'Budi Distributor', 2); // suspended
        // Create non-distributor
        $this->createMember('AGT-001', 'Citra Agent', 'AGT', 1);

        $this->getJson('/api/v1/admin/partnership/distributors')
            ->assertOk()
            ->assertJsonCount(2, 'data.results')
            ->assertJsonPath('data.results.0.code', 'DST-002')
            ->assertJsonPath('data.results.1.code', 'DST-001');

        $distributor = Member::query()->where('member_code', 'DST-001')->firstOrFail();

        $this->getJson("/api/v1/admin/partnership/distributors/{$distributor->member_id}")
            ->assertOk()
            ->assertJsonPath('data.code', 'DST-001')
            ->assertJsonPath('data.name', 'Ani Distributor');
    }

    public function test_administrator_can_manually_store_new_distributor(): void
    {
        Mail::fake();
        config()->set('initial_data.development_approval_password', 'Approve123');
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $this->setupRegion();
        MemberLevel::query()->firstOrCreate(
            ['member_level_code' => 'DST'],
            [
                'member_level_id' => 1,
                'member_level_name' => 'Distributor',
                'member_level_is_active' => 1,
            ]
        );
        MemberGroup::query()->firstOrCreate(
            ['member_group_name' => 'Distributor'],
            ['member_group_is_active' => 1]
        );

        $payload = [
            'name' => 'Doni',
            'email' => 'doni@example.test',
            'mobile_phone' => '081234567890',
            'gender' => 'Laki-laki',
            'birth_date' => '1990-01-01',
            'address' => 'Jl. KTP Doni',
            'domicile_address' => 'Jl. Domisili Doni',
            'province_id' => '35',
            'city_id' => '3578',
            'district_id' => '357801',
            'subdistrict_id' => 3578010001,
            'identity_type' => 'KTP',
            'identity_no' => '3578012345678901',
            'identity_image_url' => 'https://example.com/ktp.jpg',
            'status' => 1,
        ];

        $this->postJson('/api/v1/admin/partnership/distributors', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Doni');

        $this->assertDatabaseHas('member', [
            'member_name' => 'Doni',
            'member_mobilephone' => '+6281234567890',
            'member_status' => 1,
        ]);

        $member = Member::query()->where('member_name', 'Doni')->first();

        $this->assertSame('0001/0000/0000', $member->member_code);

        $this->assertDatabaseHas('member_address', [
            'member_address_member_id' => $member->member_id,
            'member_address_label' => 'Domisili',
            'member_address_full' => 'Jl. Domisili Doni',
        ]);

        $this->assertDatabaseHas('member_account', [
            'member_account_member_id' => $member->member_id,
            'member_account_username' => $member->member_code,
        ]);
        Mail::assertSent(MemberCredentialsMail::class, function (MemberCredentialsMail $mail) use ($member): bool {
            return $mail->username === $member->member_code
                && $mail->password === 'Approve123'
                && $mail->memberCode === $member->member_code
                && $mail->memberLevel === 'Distributor'
                && $mail->email === 'doni@example.test';
        });
    }

    private function createDistributor(string $code, string $name, int $status): Member
    {
        return $this->createMember($code, $name, 'DST', $status);
    }

    private function createMember(string $code, string $name, string $levelCode, int $status): Member
    {
        $level = MemberLevel::query()->firstOrCreate(
            ['member_level_code' => $levelCode],
            ['member_level_name' => $levelCode, 'member_level_is_active' => 1]
        );

        return Member::query()->create([
            'member_code' => $code,
            'member_member_level_id' => $level->member_level_id,
            'member_name' => $name,
            'member_email' => strtolower(str_replace(' ', '', $name)).'@example.test',
            'member_mobilephone' => '08123000'.rand(100, 999),
            'member_join_datetime' => now(),
            'member_status' => $status,
        ]);
    }

    private function createAdministrator(): SiteAdministrator
    {
        $group = SiteAdministratorGroup::query()->firstOrCreate(
            ['administrator_group_type' => 'superuser'],
            ['administrator_group_title' => 'Super Administrator', 'administrator_group_is_active' => 1]
        );

        return SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->administrator_group_id,
            'administrator_username' => 'admin.test',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Admin Test',
            'administrator_email' => 'admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }

    private function setupRegion(): void
    {
        RefProvince::query()->create(['province_id' => '35', 'province_name' => 'Jawa Timur']);
        RefCity::query()->create(['city_id' => '3578', 'city_province_id' => '35', 'city_name' => 'Surabaya', 'city_type' => 'Kota', 'city_is_active' => 1]);
        RefDistrict::query()->create(['district_id' => '357801', 'district_city_id' => '3578', 'district_name' => 'Gubeng']);
        RefSubdistrict::query()->create(['subdistrict_id' => 3578010001, 'subdistrict_district_id' => '357801', 'subdistrict_name' => 'Mojo']);
    }
}
