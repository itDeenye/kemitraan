<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\AuditTrail;
use App\Models\Config;
use App\Models\Member;
use App\Models\MemberLevel;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use Database\Seeders\PartnershipConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminMasterDataManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_data_management_requires_an_administrator(): void
    {
        $this->getJson('/api/v1/admin/system/administrators')->assertUnauthorized();
        $this->getJson('/api/v1/admin/partnership/stockists')->assertUnauthorized();
        $this->getJson('/api/v1/admin/system/configs')->assertUnauthorized();
        $this->getJson('/api/v1/admin/system/commission-configs')->assertUnauthorized();
        $this->getJson('/api/v1/admin/system/member-levels')->assertUnauthorized();
        $this->getJson('/api/v1/admin/system/audit-trails')->assertUnauthorized();
    }

    public function test_administrator_accounts_can_be_managed_safely(): void
    {
        $currentAdministrator = $this->createAdministrator();
        $this->actingAs($currentAdministrator, 'admin_api');
        $role = SiteAdministratorGroup::query()->create([
            'administrator_group_title' => 'Product Administrator',
            'administrator_group_type' => 'administrator',
            'administrator_group_is_active' => 1,
        ]);

        $response = $this->postJson('/api/v1/admin/system/administrators', [
            'role_id' => $role->getKey(),
            'username' => 'product.admin',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'name' => 'Product Admin',
            'email' => 'product.admin@example.test',
            'image_url' => 'https://cdn.example.test/admin.webp',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role.title', 'Product Administrator');
        $administratorId = $response->json('data.id');

        $administrator = SiteAdministrator::query()->findOrFail($administratorId);
        $this->assertTrue(Hash::check('Secret123', $administrator->administrator_password));

        $this->getJson('/api/v1/admin/system/administrators?search=product.admin')
            ->assertOk()
            ->assertJsonCount(1, 'data.results');

        $this->putJson("/api/v1/admin/system/administrators/{$administratorId}", [
            'role_id' => $role->getKey(),
            'username' => 'product.admin',
            'name' => 'Product Administrator',
            'email' => 'product.admin@example.test',
            'image_url' => null,
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Product Administrator')
            ->assertJsonPath('data.is_active', false);

        $this->putJson("/api/v1/admin/system/administrators/{$administratorId}/password", [
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertOk();
        $this->assertTrue(Hash::check(
            'NewSecret123',
            $administrator->refresh()->administrator_password
        ));

        $this->putJson("/api/v1/admin/system/administrators/{$currentAdministrator->getKey()}", [
            'role_id' => $currentAdministrator->administrator_administrator_group_id,
            'username' => $currentAdministrator->administrator_username,
            'name' => $currentAdministrator->administrator_name,
            'email' => $currentAdministrator->administrator_email,
            'image_url' => null,
            'is_active' => false,
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');

        $this->deleteJson("/api/v1/admin/system/administrators/{$currentAdministrator->getKey()}")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');
        $this->deleteJson("/api/v1/admin/system/administrators/{$administratorId}")->assertOk();
        $this->assertDatabaseMissing('site_administrator', ['administrator_id' => $administratorId]);
    }

    public function test_distributor_can_be_registered_as_stockist_and_soft_deleted(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->createReferenceRegion();
        $distributor = $this->createMember('DST', 'DST-0001');

        $response = $this->postJson(
            '/api/v1/admin/partnership/stockists',
            $this->stockistPayload($distributor->getKey())
        )->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.member.level.code', 'DST')
            ->assertJsonPath('data.region.city_name', 'Surabaya');
        $stockistId = $response->json('data.id');

        $this->getJson('/api/v1/admin/partnership/stockists?search=stockist%20surabaya')
            ->assertOk()
            ->assertJsonCount(1, 'data.results');

        $payload = $this->stockistPayload($distributor->getKey());
        $payload['name'] = 'Stockist Utama Surabaya';
        $payload['is_active'] = false;
        $this->putJson("/api/v1/admin/partnership/stockists/{$stockistId}", $payload)
            ->assertOk()
            ->assertJsonPath('data.name', 'Stockist Utama Surabaya')
            ->assertJsonPath('data.is_active', false);

        $agent = $this->createMember('AGT', 'AGT-0001');
        $this->postJson(
            '/api/v1/admin/partnership/stockists',
            $this->stockistPayload($agent->getKey())
        )->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonPath(
                'errors.member_id.0',
                'Member yang dipilih harus merupakan Distributor aktif.'
            );

        $this->deleteJson("/api/v1/admin/partnership/stockists/{$stockistId}")->assertOk();
        $this->assertDatabaseHas('stockist', [
            'stockist_id' => $stockistId,
            'stockist_is_active' => 0,
            'stockist_is_deleted' => 1,
        ]);
        $this->getJson("/api/v1/admin/partnership/stockists/{$stockistId}")->assertNotFound();
    }

    public function test_configuration_member_level_and_audit_trail_can_be_managed(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $config = Config::query()->create([
            'config_key' => 'purchase.rules',
            'config_value' => '{}',
            'config_type' => 'json',
            'config_created_datetime' => now(),
            'config_updated_datetime' => now(),
        ]);

        $this->putJson("/api/v1/admin/system/configs/{$config->getKey()}", [
            'type' => 'json',
            'value' => ['minimum' => 100000],
        ])->assertOk()
            ->assertJsonPath('data.scope', 'system')
            ->assertJsonPath('data.value.minimum', 100000);
        $this->assertSame(
            ['minimum' => 100000],
            json_decode($config->refresh()->config_value, true)
        );

        $level = MemberLevel::query()->where('member_level_code', 'AGT')->firstOrFail();
        $this->putJson("/api/v1/admin/system/member-levels/{$level->getKey()}", [
            'name' => 'Agent',
            'description' => 'Level kemitraan Agent',
            'min_order' => 500000,
            'point_value' => 5000,
            'sort_order' => 2,
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.code', 'AGT')
            ->assertJsonPath('data.min_order', 500000);

        $auditTrail = AuditTrail::query()->create([
            'audittrail_admin_id' => 1,
            'audittrail_admin_name' => 'Administrator',
            'audittrail_menu_name' => 'Administrator',
            'audittrail_desc' => 'Membuat administrator',
            'audittrail_act' => 'POST',
            'audittrail_payload' => json_encode([
                'username' => 'new.admin',
                'password' => 'sangat-rahasia',
            ]),
            'audittrail_results' => json_encode(['access_token' => 'token-rahasia']),
            'audittrail_ip_address' => '127.0.0.1',
            'audittrail_user_agent' => 'PHPUnit',
            'audittrail_datetime' => now(),
        ]);

        $this->getJson('/api/v1/admin/system/audit-trails')
            ->assertOk()
            ->assertJsonMissingPath('data.results.0.payload');
        $this->getJson("/api/v1/admin/system/audit-trails/{$auditTrail->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.payload.password', '[DISEMBUNYIKAN]')
            ->assertJsonPath('data.results.access_token', '[DISEMBUNYIKAN]');
    }

    public function test_commission_configurations_can_be_listed_and_updated_safely(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->seed(PartnershipConfigSeeder::class);

        $response = $this->getJson('/api/v1/admin/system/commission-configs?limit=100')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Daftar konfigurasi komisi berhasil dimuat.')
            ->assertJsonCount(3, 'data.results');
        $configs = collect($response->json('data.results'));
        $this->assertSame(['commission'], $configs->pluck('scope')->unique()->values()->all());
        $monthlyConfig = $configs->firstWhere('key', 'reward_monthly');
        $stockistConfig = $configs->firstWhere('key', 'reward_stockist');
        $partnershipConfig = $configs->firstWhere('key', 'partnership');

        $this->assertIsArray($monthlyConfig);
        $this->assertIsArray($stockistConfig);
        $this->assertIsArray($partnershipConfig);
        $this->assertDatabaseCount('config', 6);
        $this->assertSame(3, Config::query()->where('config_scope', Config::SCOPE_COMMISSION)->count());
        $this->assertSame(3, Config::query()->where('config_scope', Config::SCOPE_SYSTEM)->count());
        $this->assertSame([
            'reward_monthly_min_qty_distributor',
            'reward_monthly_min_qty_agent',
            'reward_monthly_min_qty_reseller',
        ], array_keys($monthlyConfig['value']));
        $this->assertSame([
            'reward_stockist_min_amount',
            'reward_stockist_percentage_basis_points',
        ], array_keys($stockistConfig['value']));
        $this->assertSame([
            'reward_point_per_item',
            'spread_payment_percentage',
        ], array_keys($partnershipConfig['value']));

        $this->putJson('/api/v1/admin/system/commission-configs/'.$monthlyConfig['id'], [
            'value' => [
                'reward_monthly_min_qty_distributor' => 60,
                'reward_monthly_min_qty_agent' => 40,
                'reward_monthly_min_qty_reseller' => 25,
            ],
        ])->assertOk()
            ->assertJsonPath('message', 'Konfigurasi komisi berhasil diperbarui.')
            ->assertJsonPath('data.key', 'reward_monthly')
            ->assertJsonPath('data.value.reward_monthly_min_qty_distributor', 60)
            ->assertJsonPath('data.value.reward_monthly_min_qty_agent', 40)
            ->assertJsonPath('data.value.reward_monthly_min_qty_reseller', 25);

        $this->putJson('/api/v1/admin/system/commission-configs/'.$stockistConfig['id'], [
            'value' => [
                'reward_stockist_min_amount' => 75_000_000,
                'reward_stockist_percentage_basis_points' => 300,
            ],
        ])->assertOk()
            ->assertJsonPath('data.key', 'reward_stockist')
            ->assertJsonPath('data.value.reward_stockist_min_amount', 75_000_000)
            ->assertJsonPath('data.value.reward_stockist_percentage_basis_points', 300);

        $this->putJson('/api/v1/admin/system/commission-configs/'.$partnershipConfig['id'], [
            'value' => [
                'reward_point_per_item' => 2,
                'spread_payment_percentage' => 1.5,
            ],
        ])->assertOk()
            ->assertJsonPath('data.key', 'partnership')
            ->assertJsonPath('data.value.reward_point_per_item', 2)
            ->assertJsonPath('data.value.spread_payment_percentage', 1.5);

        $this->putJson('/api/v1/admin/system/commission-configs/'.$stockistConfig['id'], [
            'value' => [
                'reward_stockist_min_amount' => 75_000_000,
                'reward_stockist_percentage_basis_points' => 10_001,
            ],
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonStructure(['errors' => ['value.reward_stockist_percentage_basis_points']]);

        $upgradeConfig = Config::query()->where('config_key', 'upgrade')->firstOrFail();
        $this->putJson('/api/v1/admin/system/commission-configs/'.$upgradeConfig->getKey(), [
            'value' => json_decode($upgradeConfig->config_value, true, 512, JSON_THROW_ON_ERROR),
        ])->assertNotFound();
    }

    public function test_business_configuration_groups_are_json_and_cannot_be_deleted(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->seed(PartnershipConfigSeeder::class);

        $response = $this->getJson('/api/v1/admin/system/configs?limit=100')
            ->assertOk()
            ->assertJsonCount(3, 'data.results')
            ->assertJsonPath('data.results.0.scope', 'system')
            ->assertJsonPath('data.results.0.type', 'json');
        $configs = collect($response->json('data.results'));
        $upgrade = $configs->firstWhere('key', 'upgrade');

        $this->assertIsArray($upgrade);
        $this->assertSame(50, $upgrade['value']['upgrade_reseller_min_qty_per_month']);
        $this->assertNull($configs->firstWhere('key', 'reward_monthly'));

        $commissionConfig = Config::query()->where('config_key', 'reward_monthly')->firstOrFail();
        $this->getJson('/api/v1/admin/system/configs/'.$commissionConfig->getKey())
            ->assertNotFound();
        $this->postJson('/api/v1/admin/system/configs', [
            'key' => 'system.dynamic',
            'type' => 'string',
            'value' => 'tidak-boleh-dibuat',
        ])->assertMethodNotAllowed();

        $configId = (int) $upgrade['id'];
        $this->putJson("/api/v1/admin/system/configs/{$configId}", [
            'type' => 'string',
            'value' => 'tidak-valid',
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');

        $this->deleteJson("/api/v1/admin/system/configs/{$configId}")
            ->assertMethodNotAllowed();
    }

    private function createAdministrator(): SiteAdministrator
    {
        $group = SiteAdministratorGroup::query()->create([
            'administrator_group_title' => 'Super Administrator',
            'administrator_group_type' => 'superuser',
            'administrator_group_is_active' => 1,
        ]);

        return SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->getKey(),
            'administrator_username' => 'master.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Master Admin',
            'administrator_email' => 'master.admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }

    private function createMember(string $levelCode, string $memberCode): Member
    {
        $level = MemberLevel::query()->where('member_level_code', $levelCode)->firstOrFail();

        return Member::query()->create([
            'member_code' => $memberCode,
            'member_member_level_id' => $level->getKey(),
            'member_parent_member_id' => 0,
            'member_name' => "Member {$levelCode}",
            'member_email' => mb_strtolower($memberCode).'@example.test',
            'member_mobilephone' => '081234567890',
            'member_join_datetime' => now(),
            'member_status' => 1,
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
    private function stockistPayload(int $memberId): array
    {
        return [
            'member_id' => $memberId,
            'name' => 'Stockist Surabaya',
            'email' => 'stockist@example.test',
            'address' => 'Jalan DNY Nomor 1',
            'mobile_phone' => '081234567890',
            'image_url' => 'https://cdn.example.test/stockist.webp',
            'province_id' => 11,
            'city_id' => 1101,
            'district_id' => 110101,
            'subdistrict_id' => 1,
            'latitude' => -7.2575,
            'longitude' => 112.7521,
            'note' => 'Buka pukul 08.00-17.00',
            'is_active' => true,
        ];
    }
}
