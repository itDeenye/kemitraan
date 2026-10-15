<?php

namespace Tests\Feature\Api\V1\Admin;

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

class AdminDistributorDomicileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $group = SiteAdministratorGroup::query()->firstOrCreate(
            ['administrator_group_type' => 'superuser'],
            ['administrator_group_title' => 'Super Administrator', 'administrator_group_is_active' => 1]
        );
        $this->actingAs(SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->getKey(),
            'administrator_username' => 'domicile.admin', 'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Admin', 'administrator_email' => 'admin@example.test',
            'administrator_image' => '', 'administrator_is_active' => 1,
        ]), 'admin_api');
        MemberLevel::query()->firstOrCreate(['member_level_code' => 'DST'],
            ['member_level_name' => 'Distributor', 'member_level_is_active' => 1]);
        MemberGroup::query()->firstOrCreate(['member_group_name' => 'Distributor'], ['member_group_is_active' => 1]);
        foreach ([['35', '3578', '357801', 3578010001], ['32', '3273', '327301', 3273010001]] as [$province, $city, $district, $subdistrict]) {
            RefProvince::query()->create(['province_id' => $province, 'province_name' => $province]);
            RefCity::query()->create(['city_id' => $city, 'city_province_id' => $province,
                'city_name' => $city, 'city_type' => 'Kota', 'city_is_active' => 1]);
            RefDistrict::query()->create(['district_id' => $district, 'district_city_id' => $city, 'district_name' => $district]);
            RefSubdistrict::query()->create(['subdistrict_id' => $subdistrict,
                'subdistrict_district_id' => $district, 'subdistrict_name' => (string) $subdistrict]);
        }
    }

    public function test_distributor_stores_domicile_region_independently_of_ktp(): void
    {
        $id = $this->postJson('/api/v1/admin/partnership/distributors', [...$this->payload(), ...$this->domicile()])
            ->assertCreated()->json('data.id');
        $this->assertDatabaseHas('member_address', ['member_address_member_id' => $id,
            'member_address_label' => 'KTP', 'member_address_province_id' => '35', 'member_address_city_id' => '3578',
            'member_address_district_id' => '357801', 'member_address_subdistrict_id' => 3578010001]);
        $this->assertDatabaseHas('member_address', ['member_address_member_id' => $id,
            'member_address_label' => 'Domisili', 'member_address_full' => 'Jl. Domisili',
            'member_address_province_id' => '32', 'member_address_city_id' => '3273',
            'member_address_district_id' => '327301', 'member_address_subdistrict_id' => 3273010001,
            'member_address_is_default' => 1]);
    }

    public function test_omitted_domicile_region_preserves_legacy_ktp_fallback(): void
    {
        $id = $this->postJson('/api/v1/admin/partnership/distributors', $this->payload())->assertCreated()->json('data.id');
        $this->assertDatabaseHas('member_address', ['member_address_member_id' => $id,
            'member_address_label' => 'Domisili', 'member_address_full' => 'Jl. Domisili',
            'member_address_province_id' => '35', 'member_address_city_id' => '3578',
            'member_address_district_id' => '357801', 'member_address_subdistrict_id' => 3578010001]);
    }

    public function test_partial_domicile_region_is_rejected_instead_of_mixing_addresses(): void
    {
        $this->postJson('/api/v1/admin/partnership/distributors', [...$this->payload(), 'domicile_province_id' => '32'])
            ->assertUnprocessable()->assertJsonValidationErrors(['domicile_city_id', 'domicile_district_id', 'domicile_subdistrict_id']);
    }

    public function test_domicile_and_ktp_hierarchy_are_validated_independently(): void
    {
        $this->postJson('/api/v1/admin/partnership/distributors', [...$this->payload(), ...$this->domicile(), 'domicile_city_id' => '3578'])
            ->assertUnprocessable()->assertJsonValidationErrors('domicile_province_id');
        $this->postJson('/api/v1/admin/partnership/distributors', [...$this->payload(), ...$this->domicile(), 'city_id' => '3273'])
            ->assertUnprocessable()->assertJsonValidationErrors('province_id');
    }

    private function payload(): array
    {
        return ['name' => 'Domicile Distributor', 'mobile_phone' => '081234567890',
            'gender' => 'Laki-laki', 'birth_date' => '1990-01-01', 'status' => 1,
            'identity_type' => 'KTP', 'identity_no' => '3578012345678901',
            'address' => 'Jl. KTP', 'domicile_address' => 'Jl. Domisili',
            'province_id' => '35', 'city_id' => '3578', 'district_id' => '357801', 'subdistrict_id' => 3578010001];
    }

    private function domicile(): array
    {
        return ['domicile_province_id' => '32', 'domicile_city_id' => '3273',
            'domicile_district_id' => '327301', 'domicile_subdistrict_id' => 3273010001];
    }
}
