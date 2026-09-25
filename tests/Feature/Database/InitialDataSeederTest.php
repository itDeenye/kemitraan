<?php

namespace Tests\Feature\Database;

use App\Models\Warehouse;
use App\Support\PhoneNumber;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\InitialAdministratorSeeder;
use Database\Seeders\InitialCompanyBankSeeder;
use Database\Seeders\InitialMemberSeeder;
use Database\Seeders\InitialWarehouseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InitialDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_users_and_current_administrator_menus_are_seeded(): void
    {
        config([
            'initial_data.administrator.password' => 'AdminTest123',
            'initial_data.member.password' => 'MemberTest123',
            'initial_data.member.pin' => '123456',
        ]);

        $this->seed(AccessControlSeeder::class);
        $this->seed(InitialAdministratorSeeder::class);
        $this->seed(InitialMemberSeeder::class);
        $this->seed(InitialWarehouseSeeder::class);
        $this->seed(InitialCompanyBankSeeder::class);

        $this->assertDatabaseHas('site_administrator', ['administrator_id' => 1]);
        $this->assertDatabaseHas('member', ['member_id' => 1]);
        $this->assertDatabaseHas('member_account', [
            'member_account_id' => 1,
            'member_account_member_id' => 1,
        ]);
        $this->assertDatabaseHas('member_address', [
            'member_address_id' => 1,
            'member_address_member_id' => 1,
            'member_address_label' => 'Alamat Utama',
            'member_address_is_default' => 1,
        ]);
        $this->assertDatabaseHas('member_bank_account', [
            'member_bank_account_id' => 1,
            'member_bank_account_member_id' => 1,
            'member_bank_account_is_active' => 1,
            'member_bank_account_is_default' => 1,
        ]);
        $this->assertDatabaseHas('warehouse', [
            'warehouse_id' => 1,
            'warehouse_name' => 'Warehouse Utama DNY',
            'warehouse_legal_name' => 'PT Deenye Berkah Abadi',
            'warehouse_npwp' => '01.234.567.8-901.000',
            'warehouse_phone' => '+6281110000101',
            'warehouse_email' => 'warehouse@dny.example.test',
            'warehouse_logo' => '/logo.png',
            'warehouse_address' => 'Jalan Teuku Umar Barat No. 88, Dauh Puri, Denpasar Barat, Denpasar, Bali 80113',
            'warehouse_latitude' => '-8.6705',
            'warehouse_longitude' => '115.2126',
            'warehouse_is_active' => 1,
        ]);
        $warehouse = Warehouse::query()->findOrFail(1);
        $this->assertTrue(PhoneNumber::isValid($warehouse->warehouse_phone));
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
        $this->assertDatabaseHas('site_administrator_menu', [
            'administrator_menu_id' => 121,
            'administrator_menu_title' => 'Reward Tahunan',
            'administrator_menu_link' => '/rewards/annual',
            'administrator_menu_icon' => 'mdi-star-circle',
        ]);
        $this->assertDatabaseHas('site_administrator_menu', [
            'administrator_menu_id' => 153,
            'administrator_menu_title' => 'Manajemen STC',
            'administrator_menu_link' => '#',
        ]);
        $this->assertDatabaseHas('site_administrator_menu', [
            'administrator_menu_id' => 159,
            'administrator_menu_title' => 'Laporan Pencapaian Poin',
            'administrator_menu_link' => '/rewards/point-achievement-report',
        ]);
        $this->assertDatabaseHas('site_administrator_menu', [
            'administrator_menu_id' => 160,
            'administrator_menu_par_id' => 7,
            'administrator_menu_title' => 'Screening Stok',
            'administrator_menu_link' => '/transactions/stock-screening',
            'administrator_menu_icon' => 'mdi-clipboard-text-search-outline',
            'administrator_menu_order_by' => 2,
        ]);
        $this->assertDatabaseHas('site_administrator_menu', [
            'administrator_menu_id' => 161,
            'administrator_menu_par_id' => 0,
            'administrator_menu_title' => 'Analitik User',
            'administrator_menu_link' => '/user-analytic',
            'administrator_menu_icon' => 'mdi-poll',
            'administrator_menu_order_by' => 13,
        ]);
        $this->assertDatabaseHas('site_administrator_menu', [
            'administrator_menu_id' => 162,
            'administrator_menu_par_id' => 12,
            'administrator_menu_title' => 'Konfigurasi Komisi',
            'administrator_menu_link' => '/system/commission-config',
            'administrator_menu_icon' => 'mdi-application-cog-outline',
            'administrator_menu_order_by' => 4,
        ]);
        $this->assertDatabaseHas('site_administrator_menu', [
            'administrator_menu_id' => 163,
            'administrator_menu_par_id' => 12,
            'administrator_menu_title' => 'Konfigurasi Kemitraan',
            'administrator_menu_link' => '/system/partnership-config',
            'administrator_menu_icon' => 'mdi-account-group-outline',
            'administrator_menu_order_by' => 5,
        ]);
        $this->assertDatabaseHas('site_administrator_menu', [
            'administrator_menu_id' => 164,
            'administrator_menu_par_id' => 12,
            'administrator_menu_title' => 'Trail Log',
            'administrator_menu_link' => '/system/trail-log',
            'administrator_menu_icon' => 'mdi-clock-edit-outline',
            'administrator_menu_order_by' => 6,
        ]);
        $this->assertDatabaseHas('site_administrator_menu', [
            'administrator_menu_id' => 114,
            'administrator_menu_order_by' => 4,
        ]);
        $expectedMenuIds = [
            1, 2, 3, 4, 5, 6, 7, 10, 12,
            101, 102, 103, 105, 106, 108, 109, 113, 114,
            121, 122, 123, 128, 129, 133, 134, 135, 136, 137,
            138, 139, 140, 143, 151, 152, 153,
            154, 155, 157, 158, 159, 160, 161, 162, 163, 164,
        ];
        $expectedAdministratorMenuIds = [
            1, 6, 101, 102, 103, 105, 106, 108, 109,
            113, 114, 121, 122, 123, 128, 129, 133, 134, 135,
            136, 137, 138, 139, 140, 143, 151,
            152, 154, 155, 157, 158, 159, 160, 161, 162, 163, 164,
        ];
        $this->assertSame(
            $expectedMenuIds,
            DB::table('site_administrator_menu')
                ->orderBy('administrator_menu_id')
                ->pluck('administrator_menu_id')
                ->map(fn (mixed $menuId): int => (int) $menuId)
                ->all(),
        );
        $this->assertSame(
            $expectedAdministratorMenuIds,
            DB::table('site_administrator_privilege')
                ->where('administrator_privilege_administrator_group_id', 2)
                ->orderBy('administrator_privilege_administrator_menu_id')
                ->pluck('administrator_privilege_administrator_menu_id')
                ->map(fn (mixed $menuId): int => (int) $menuId)
                ->all(),
        );
        $this->assertDatabaseMissing('site_administrator_menu', [
            'administrator_menu_id' => 107,
        ]);
        $this->assertDatabaseMissing('site_administrator_menu', [
            'administrator_menu_id' => 110,
        ]);
        $this->assertDatabaseMissing('site_administrator_menu', [
            'administrator_menu_id' => 147,
        ]);
        $this->assertDatabaseMissing('site_administrator_menu', [
            'administrator_menu_id' => 130,
        ]);
        $this->assertDatabaseMissing('site_administrator_menu', [
            'administrator_menu_id' => 131,
        ]);
        $this->assertDatabaseMissing('site_administrator_menu', [
            'administrator_menu_id' => 150,
        ]);
        $this->assertDatabaseMissing('site_administrator_menu', [
            'administrator_menu_id' => 156,
        ]);
        foreach ([8, 117, 118, 142] as $removedReportMenuId) {
            $this->assertDatabaseMissing('site_administrator_menu', [
                'administrator_menu_id' => $removedReportMenuId,
            ]);
            $this->assertDatabaseMissing('site_administrator_privilege', [
                'administrator_privilege_administrator_menu_id' => $removedReportMenuId,
            ]);
        }
        $this->assertFalse(Schema::hasTable('member_menu'));
        $this->assertFalse(Schema::hasTable('member_privilege'));
        $this->assertFalse(Schema::hasTable('bonus'));
        $this->assertFalse(Schema::hasTable('bonus_log'));
    }

    public function test_initial_warehouse_uses_the_canonical_bali_location(): void
    {
        DB::table('ref_province')->insert([
            'province_id' => '1',
            'province_name' => 'Bali',
            'province_is_active' => 1,
        ]);
        DB::table('ref_city')->insert([
            'city_id' => '114',
            'city_province_id' => '1',
            'city_name' => 'Denpasar',
            'city_type' => 'Kota',
            'city_is_active' => 1,
        ]);
        DB::table('ref_district')->insert([
            'district_id' => '1573',
            'district_city_id' => '114',
            'district_name' => 'Denpasar Barat',
        ]);
        DB::table('ref_subdistrict')->insert([
            'subdistrict_id' => 26027,
            'subdistrict_district_id' => 1573,
            'subdistrict_name' => 'Dauh Puri',
            'subdistrict_zip_code' => 80113,
        ]);

        $this->seed(InitialWarehouseSeeder::class);

        $warehouse = Warehouse::query()
            ->with(['province', 'city', 'district', 'subdistrict'])
            ->findOrFail(1);
        $this->assertSame('Bali', $warehouse->province?->province_name);
        $this->assertSame('Denpasar', $warehouse->city?->city_name);
        $this->assertSame('Denpasar Barat', $warehouse->district?->district_name);
        $this->assertSame('Dauh Puri', $warehouse->subdistrict?->subdistrict_name);
        $this->assertSame(80113, $warehouse->subdistrict?->subdistrict_zip_code);
    }

    public function test_reseeding_removes_obsolete_administrator_menu_and_privilege(): void
    {
        $this->seed(AccessControlSeeder::class);

        DB::table('site_administrator_menu')->insert([
            'administrator_menu_id' => 110,
            'administrator_menu_par_id' => 5,
            'administrator_menu_title' => 'Jaringan Mitra',
            'administrator_menu_description' => '',
            'administrator_menu_link' => '/partnership/genealogy',
            'administrator_menu_icon' => 'mdi-sitemap',
            'administrator_menu_class' => '',
            'administrator_menu_order_by' => 2,
            'administrator_menu_is_active' => 1,
        ]);
        DB::table('site_administrator_privilege')->insert([
            'administrator_privilege_administrator_group_id' => 2,
            'administrator_privilege_administrator_menu_id' => 110,
        ]);

        $this->seed(AccessControlSeeder::class);

        $this->assertDatabaseMissing('site_administrator_menu', ['administrator_menu_id' => 110]);
        $this->assertDatabaseMissing('site_administrator_privilege', [
            'administrator_privilege_administrator_menu_id' => 110,
        ]);
        $this->assertDatabaseHas('site_administrator_menu', [
            'administrator_menu_id' => 164,
            'administrator_menu_title' => 'Trail Log',
        ]);
        $this->assertDatabaseHas('site_administrator_privilege', [
            'administrator_privilege_administrator_group_id' => 2,
            'administrator_privilege_administrator_menu_id' => 164,
        ]);
    }
}
