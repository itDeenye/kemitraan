<?php

namespace Tests\Feature\Api\V1\Reference;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_data_is_public_and_region_results_are_filtered_by_parent(): void
    {
        $this->createReferenceData();

        $this->getJson('/api/v1/references/provinces')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', '11');

        $this->getJson('/api/v1/references/cities?province_id=11')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Surabaya');

        $this->getJson('/api/v1/references/districts?city_id=1101')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Tegalsari');

        $this->getJson('/api/v1/references/subdistricts?district_id=110101')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Keputran')
            ->assertJsonPath('data.0.postal_code', 60265);
    }

    public function test_only_active_banks_and_countries_are_returned(): void
    {
        $this->createReferenceData();

        $this->getJson('/api/v1/references/banks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BCA');

        $this->getJson('/api/v1/references/countries')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'ID')
            ->assertJsonPath('data.0.phone_code', '+62');
    }

    public function test_region_parent_parameter_is_required_and_validated_in_indonesian(): void
    {
        $this->getJson('/api/v1/references/cities')
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonPath('errors.province_id.0', 'Provinsi wajib diisi.');
    }

    private function createReferenceData(): void
    {
        DB::table('ref_country')->insert([
            [
                'country_id' => 1,
                'country_iso_code' => 'ID',
                'country_phone_code' => '+62',
                'country_name' => 'Indonesia',
                'country_flag' => 'id.svg',
                'country_is_active' => '1',
            ],
            [
                'country_id' => 2,
                'country_iso_code' => 'ZZ',
                'country_phone_code' => '+0',
                'country_name' => 'Tidak Aktif',
                'country_flag' => '',
                'country_is_active' => '0',
            ],
        ]);
        DB::table('ref_province')->insert([
            [
                'province_id' => '11',
                'province_name' => 'Jawa Timur',
                'province_is_active' => '1',
            ],
            [
                'province_id' => '12',
                'province_name' => 'Provinsi Tidak Aktif',
                'province_is_active' => '0',
            ],
        ]);
        DB::table('ref_city')->insert([
            [
                'city_id' => '1101',
                'city_province_id' => '11',
                'city_name' => 'Surabaya',
                'city_type' => 'Kota',
                'city_is_active' => 1,
            ],
            [
                'city_id' => '1102',
                'city_province_id' => '11',
                'city_name' => 'Kota Tidak Aktif',
                'city_type' => 'Kota',
                'city_is_active' => 0,
            ],
        ]);
        DB::table('ref_district')->insert([
            'district_id' => '110101',
            'district_city_id' => '1101',
            'district_name' => 'Tegalsari',
        ]);
        DB::table('ref_subdistrict')->insert([
            'subdistrict_id' => 1,
            'subdistrict_district_id' => 110101,
            'subdistrict_name' => 'Keputran',
            'subdistrict_zip_code' => 60265,
        ]);
        DB::table('ref_bank')->insert([
            [
                'bank_id' => 1,
                'bank_code' => 'BCA',
                'bank_name' => 'Bank Central Asia',
                'bank_logo' => 'bca.svg',
                'bank_is_active' => 1,
            ],
            [
                'bank_id' => 2,
                'bank_code' => 'OFF',
                'bank_name' => 'Bank Tidak Aktif',
                'bank_logo' => '',
                'bank_is_active' => 0,
            ],
        ]);
    }
}
