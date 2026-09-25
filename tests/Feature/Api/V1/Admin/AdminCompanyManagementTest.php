<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_management_requires_an_administrator(): void
    {
        $this->getJson('/api/v1/admin/company/banks')->assertUnauthorized();
        $this->getJson('/api/v1/admin/company/warehouses')->assertUnauthorized();
    }

    public function test_administrator_can_manage_company_banks(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createReferenceBank();

        $response = $this->postJson('/api/v1/admin/company/banks', [
            'type' => 'company',
            'bank_id' => 1,
            'account_name' => 'PT Deenye Berkah Abadi',
            'account_number' => '1234567890',
            'is_active' => true,
        ])->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.bank.name', 'Bank Central Asia')
            ->assertJsonPath('data.type', 'company')
            ->assertJsonPath('data.account_number', '1234567890');
        $bankId = $response->json('data.id');

        $this->getJson('/api/v1/admin/company/banks?search=central')
            ->assertOk()
            ->assertJsonCount(1, 'data.results');
        $this->getJson("/api/v1/admin/company/banks/{$bankId}")
            ->assertOk()
            ->assertJsonPath('data.account_name', 'PT Deenye Berkah Abadi');

        $this->putJson("/api/v1/admin/company/banks/{$bankId}", [
            'type' => 'spread_payment',
            'bank_id' => 1,
            'account_name' => 'PT DBA',
            'account_number' => '1234567890',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.account_name', 'PT DBA')
            ->assertJsonPath('data.type', 'spread_payment')
            ->assertJsonPath('data.is_active', false);

        $this->postJson('/api/v1/admin/company/banks', [
            'type' => 'company',
            'bank_id' => 1,
            'account_name' => 'Duplikat',
            'account_number' => '1234567890',
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonPath('errors.account_number.0', 'Nomor rekening sudah digunakan.');

        $this->deleteJson("/api/v1/admin/company/banks/{$bankId}")
            ->assertOk()
            ->assertJsonPath('message', 'Bank perusahaan berhasil dihapus.');
        $this->assertDatabaseMissing('bank_company', ['bank_company_id' => $bankId]);
    }

    public function test_administrator_can_manage_warehouses_and_used_warehouse_cannot_be_deleted(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createReferenceRegion();

        $response = $this->postJson('/api/v1/admin/company/warehouses', $this->warehousePayload())
            ->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Warehouse Surabaya')
            ->assertJsonPath('data.region.city_name', 'Surabaya');
        $warehouseId = $response->json('data.id');

        $this->getJson('/api/v1/admin/company/warehouses?search=surabaya')
            ->assertOk()
            ->assertJsonCount(1, 'data.results');
        $this->getJson("/api/v1/admin/company/warehouses/{$warehouseId}")
            ->assertOk()
            ->assertJsonPath('data.address', 'Jalan DNY Nomor 1');

        $updatedPayload = $this->warehousePayload();
        $updatedPayload['phone'] = '031999999';
        $updatedPayload['logo_url'] = 'https://cdn.example.test/company/logo.webp';
        $this->putJson("/api/v1/admin/company/warehouses/{$warehouseId}", $updatedPayload)
            ->assertOk()
            ->assertJsonPath('data.phone', '031999999')
            ->assertJsonPath('data.logo', 'https://cdn.example.test/company/logo.webp');

        DB::table('warehouse_stock')->insert([
            'warehouse_stock_warehouse_id' => $warehouseId,
            'warehouse_stock_product_id' => 99,
            'warehouse_stock_balance' => 1,
        ]);

        $this->deleteJson("/api/v1/admin/company/warehouses/{$warehouseId}")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error')
            ->assertJsonPath(
                'message',
                'Gudang masih digunakan pada stok atau transaksi dan tidak dapat dihapus.'
            );

        DB::table('warehouse_stock')->delete();
        $this->deleteJson("/api/v1/admin/company/warehouses/{$warehouseId}")->assertOk();
        $this->assertDatabaseMissing('warehouse', ['warehouse_id' => $warehouseId]);
    }

    public function test_warehouse_region_hierarchy_must_be_valid(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createReferenceRegion();
        DB::table('ref_city')->insert([
            'city_id' => '1201',
            'city_province_id' => '12',
            'city_name' => 'Kota Lain',
            'city_type' => 'Kota',
        ]);

        $payload = $this->warehousePayload();
        $payload['city_id'] = '1201';

        $this->postJson('/api/v1/admin/company/warehouses', $payload)
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.province_id.0',
                'Wilayah provinsi, kota, kecamatan, dan kelurahan tidak saling sesuai.'
            );
    }

    private function createAdministrator(): SiteAdministrator
    {
        $group = SiteAdministratorGroup::query()->create([
            'administrator_group_title' => 'Administrator',
            'administrator_group_type' => 'administrator',
            'administrator_group_is_active' => 1,
        ]);

        return SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->getKey(),
            'administrator_username' => 'company.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Company Admin',
            'administrator_email' => 'company.admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }

    private function createReferenceBank(): void
    {
        DB::table('ref_bank')->insert([
            'bank_id' => 1,
            'bank_code' => 'BCA',
            'bank_name' => 'Bank Central Asia',
            'bank_logo' => 'bca.png',
            'bank_is_active' => 1,
        ]);
    }

    private function createReferenceRegion(): void
    {
        DB::table('ref_province')->insert([
            'province_id' => '11',
            'province_name' => 'Jawa Timur',
        ]);
        DB::table('ref_city')->insert([
            'city_id' => '1101',
            'city_province_id' => '11',
            'city_name' => 'Surabaya',
            'city_type' => 'Kota',
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
    }

    /** @return array<string, mixed> */
    private function warehousePayload(): array
    {
        return [
            'name' => 'Warehouse Surabaya',
            'legal_name' => 'PT Deenye Berkah Abadi',
            'npwp' => '01.234.567.8-901.000',
            'phone' => '031123456',
            'email' => 'warehouse@example.test',
            'logo_url' => null,
            'address' => 'Jalan DNY Nomor 1',
            'province_id' => '11',
            'city_id' => '1101',
            'district_id' => '110101',
            'subdistrict_id' => 1,
            'latitude' => -7.2575,
            'longitude' => 112.7521,
            'is_active' => true,
        ];
    }
}
