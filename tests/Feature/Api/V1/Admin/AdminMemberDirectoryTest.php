<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Mail\MemberPasswordResetByAdminMail;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberAddress;
use App\Models\MemberBankAccount;
use App\Models\MemberGroup;
use App\Models\MemberStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RewardPointMonthly;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use App\Models\Trx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminMemberDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_list_filter_and_view_non_deleted_members(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $reseller = $this->createMember('MEM-001', 'Anisa', 'reseller', 1);
        $reseller->update([
            'member_gender' => 'Perempuan',
            'member_birth_date' => '1994-05-17',
            'member_identity_type' => 'KTP',
            'member_identity_no' => '3214015705940001',
            'member_identity_image' => 'https://cdn.example.test/identity/anisa.webp',
            'member_identity_image_filename' => 'anisa.webp',
            'member_nib' => '1234567890123',
            'member_image' => 'https://cdn.example.test/member/anisa.webp',
            'member_image_filename' => 'anisa-profile.webp',
            'member_instagram' => '@anisa',
            'member_facebook' => 'anisa.dny',
            'member_tiktok' => '@anisa.dny',
        ]);
        $this->createMember('MEM-002', 'Dewi', 'agent', 2);
        $this->createMember('MEM-003', 'Deleted Member', 'distributor', 3);

        $this->getJson('/api/v1/admin/partnership/members?search=anisa&field_search=name&filter[level_id]=3&sort=name')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.code', 'MEM-001')
            ->assertJsonPath('data.results.0.status.label', 'active');

        $this->getJson('/api/v1/admin/partnership/members?limit=100')
            ->assertOk()
            ->assertJsonCount(2, 'data.results')
            ->assertJsonPath('data.pagination.total_data', 2);

        $this->getJson("/api/v1/admin/partnership/members/{$reseller->member_id}")
            ->assertOk()
            ->assertJsonPath('data.username', 'member.mem-001')
            ->assertJsonPath('data.account.username', 'member.mem-001')
            ->assertJsonPath('data.account.role.name', 'Member')
            ->assertJsonPath('data.account.last_login_at', null)
            ->assertJsonPath('data.gender', 'Perempuan')
            ->assertJsonPath('data.birth_date', '1994-05-17')
            ->assertJsonPath('data.level.code', 'RSL')
            ->assertJsonPath('data.level.name', 'Reseller')
            ->assertJsonPath('data.identity.type', 'KTP')
            ->assertJsonPath('data.identity.number', '3214015705940001')
            ->assertJsonPath('data.identity.image', 'https://cdn.example.test/identity/anisa.webp')
            ->assertJsonPath('data.identity.image_filename', 'anisa.webp')
            ->assertJsonPath('data.nib', '1234567890123')
            ->assertJsonPath('data.image', 'https://cdn.example.test/member/anisa.webp')
            ->assertJsonPath('data.image_filename', 'anisa-profile.webp')
            ->assertJsonPath('data.social_media.instagram', '@anisa')
            ->assertJsonPath('data.social_media.facebook', 'anisa.dny')
            ->assertJsonPath('data.social_media.tiktok', '@anisa.dny')
            ->assertJsonPath('data.is_stockist', false)
            ->assertJsonPath('data.stockist', null)
            ->assertJsonCount(1, 'data.addresses')
            ->assertJsonPath('data.addresses.0.full_address', 'Purwakarta')
            ->assertJsonPath('data.addresses.0.region.province_id', '9')
            ->assertJsonPath('data.addresses.0.region.province_name', 'Jawa Barat')
            ->assertJsonPath('data.addresses.0.region.city_id', '376')
            ->assertJsonPath('data.addresses.0.region.city_name', 'Purwakarta')
            ->assertJsonPath('data.addresses.0.region.city_type', 'Kabupaten')
            ->assertJsonPath('data.addresses.0.region.district_id', '5218')
            ->assertJsonPath('data.addresses.0.region.district_name', 'Purwakarta')
            ->assertJsonPath('data.addresses.0.region.subdistrict_id', 60281)
            ->assertJsonPath('data.addresses.0.region.subdistrict_name', 'Nagrikaler')
            ->assertJsonPath('data.addresses.0.region.postal_code', 41115)
            ->assertJsonPath('data.addresses.0.region.country_id', 1)
            ->assertJsonPath('data.addresses.0.region.country_name', 'Indonesia')
            ->assertJsonCount(0, 'data.bank_accounts')
            ->assertJsonMissingPath('data.address')
            ->assertJsonMissingPath('data.bank');
    }

    public function test_administrator_can_replace_multiple_addresses_and_bank_accounts(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $member = $this->createMember('MEM-004', 'Rina', 'reseller', 1);
        $existingAddress = $member->addresses()->sole();
        $obsoleteAddress = MemberAddress::query()->create([
            'member_address_member_id' => $member->getKey(),
            'member_address_label' => 'Alamat Lama',
            'member_address_recipient' => 'Rina',
            'member_address_phone' => '+628123456789',
            'member_address_full' => 'Alamat yang akan dihapus',
            'member_address_province_id' => 9,
            'member_address_city_id' => 376,
            'member_address_district_id' => 5218,
            'member_address_subdistrict_id' => 60281,
            'member_address_country_id' => 1,
            'member_address_is_default' => 0,
        ]);
        DB::table('ref_bank')->insert([
            'bank_id' => 1,
            'bank_code' => 'BCA',
            'bank_name' => 'Bank Central Asia',
            'bank_is_active' => 1,
        ]);
        $existingBank = MemberBankAccount::query()->create([
            'member_bank_account_member_id' => $member->getKey(),
            'member_bank_account_bank_id' => 1,
            'member_bank_account_name' => 'Rina',
            'member_bank_account_number' => '1111111111',
            'member_bank_account_is_active' => 1,
            'member_bank_account_is_default' => 1,
        ]);
        $obsoleteBank = MemberBankAccount::query()->create([
            'member_bank_account_member_id' => $member->getKey(),
            'member_bank_account_bank_id' => 1,
            'member_bank_account_name' => 'Rina Lama',
            'member_bank_account_number' => '2222222222',
            'member_bank_account_is_active' => 1,
            'member_bank_account_is_default' => 0,
        ]);

        $this->putJson("/api/v1/admin/partnership/members/{$member->getKey()}", [
            'name' => 'Rina Diperbarui',
            'email' => 'rina@example.test',
            'mobile_phone' => '081234567890',
            'gender' => 'Perempuan',
            'birth_date' => '1990-01-01',
            'status' => 1,
            'addresses' => [
                [
                    'id' => $existingAddress->getKey(),
                    'label' => 'Rumah',
                    'recipient' => 'Rina Diperbarui',
                    'phone' => '081234567890',
                    'full_address' => 'Jalan Rumah Baru',
                    'province_id' => '9',
                    'city_id' => '376',
                    'district_id' => '5218',
                    'subdistrict_id' => 60281,
                    'country_id' => 1,
                    'is_default' => false,
                ],
                [
                    'label' => 'Gudang',
                    'recipient' => 'Admin Gudang',
                    'phone' => '081234567891',
                    'full_address' => 'Jalan Gudang Baru',
                    'province_id' => '9',
                    'city_id' => '376',
                    'district_id' => '5218',
                    'subdistrict_id' => 60281,
                    'country_id' => 1,
                    'is_default' => true,
                ],
            ],
            'bank_accounts' => [
                [
                    'id' => $existingBank->getKey(),
                    'bank_id' => 1,
                    'account_name' => 'Rina Diperbarui',
                    'account_number' => '3333333333',
                    'city' => 'Purwakarta',
                    'branch' => 'Pusat',
                    'is_active' => true,
                    'is_default' => true,
                ],
                [
                    'bank_id' => 1,
                    'account_name' => 'Rina Cadangan',
                    'account_number' => '4444444444',
                    'city' => 'Bandung',
                    'branch' => null,
                    'is_active' => false,
                    'is_default' => false,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.name', 'Rina Diperbarui')
            ->assertJsonCount(2, 'data.addresses')
            ->assertJsonPath('data.addresses.0.label', 'Gudang')
            ->assertJsonPath('data.addresses.0.is_default', true)
            ->assertJsonPath('data.addresses.1.id', $existingAddress->getKey())
            ->assertJsonPath('data.addresses.1.phone', '+6281234567890')
            ->assertJsonCount(2, 'data.bank_accounts')
            ->assertJsonPath('data.bank_accounts.0.id', $existingBank->getKey())
            ->assertJsonPath('data.bank_accounts.0.account_number', '3333333333')
            ->assertJsonPath('data.bank_accounts.0.is_default', true)
            ->assertJsonPath('data.bank_accounts.1.is_active', false)
            ->assertJsonMissingPath('data.address')
            ->assertJsonMissingPath('data.bank');

        $this->assertDatabaseMissing('member_address', [
            'member_address_id' => $obsoleteAddress->getKey(),
        ]);
        $this->assertDatabaseMissing('member_bank_account', [
            'member_bank_account_id' => $obsoleteBank->getKey(),
        ]);
    }

    public function test_member_update_rejects_legacy_single_address_and_bank_payload(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $member = $this->createMember('MEM-005', 'Sari', 'reseller', 1);

        $this->putJson("/api/v1/admin/partnership/members/{$member->getKey()}", [
            'name' => 'Sari',
            'email' => 'sari@example.test',
            'mobile_phone' => '081234567890',
            'gender' => 'Perempuan',
            'birth_date' => '1990-01-01',
            'address' => 'Payload lama',
            'bank_id' => 1,
            'status' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['addresses', 'bank_accounts', 'address', 'bank_id']);
    }

    public function test_administrator_can_deactivate_member_and_move_network_and_unpaid_rewards_immediately(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $oldAgent = $this->createMember('MEM-101', 'Agent Lama', 'agent', 1);
        $replacementDistributor = $this->createMember('MEM-102', 'Distributor Baru', 'distributor', 1);
        $reseller = $this->createMember('MEM-103', 'Reseller Aktif', 'reseller', 1);
        $reseller->update(['member_parent_member_id' => $oldAgent->getKey()]);
        $nestedReseller = $this->createMember('MEM-104', 'Reseller Turunan', 'reseller', 1);
        $nestedReseller->update(['member_parent_member_id' => $reseller->getKey()]);

        $unpaidReward = RewardPointMonthly::query()->create([
            'reward_point_monthly_upline_id' => $oldAgent->getKey(),
            'reward_point_monthly_upline_level_id' => 2,
            'reward_point_monthly_member_id' => $reseller->getKey(),
            'reward_point_monthly_member_level_id' => 3,
            'reward_point_monthly_year' => 2026,
            'reward_point_monthly_month' => 8,
            'reward_point_monthly_total_qty' => 50,
            'reward_point_monthly_bonus_value' => 250000,
            'reward_point_monthly_is_processed' => 0,
        ]);
        $paidReward = RewardPointMonthly::query()->create([
            'reward_point_monthly_upline_id' => $oldAgent->getKey(),
            'reward_point_monthly_upline_level_id' => 2,
            'reward_point_monthly_member_id' => $reseller->getKey(),
            'reward_point_monthly_member_level_id' => 3,
            'reward_point_monthly_year' => 2026,
            'reward_point_monthly_month' => 7,
            'reward_point_monthly_total_qty' => 50,
            'reward_point_monthly_bonus_value' => 250000,
            'reward_point_monthly_is_processed' => 1,
            'reward_point_monthly_processed_datetime' => now(),
        ]);

        $this->getJson("/api/v1/admin/partnership/members/{$oldAgent->getKey()}/deactivation-options")
            ->assertOk()
            ->assertJsonPath('data.requires_replacement_sponsor', true)
            ->assertJsonPath('data.can_deactivate', true)
            ->assertJsonPath('data.summary.direct_downline_count', 1)
            ->assertJsonPath('data.summary.outstanding_reward_count', 1)
            ->assertJsonFragment(['id' => $replacementDistributor->getKey()]);

        $this->postJson("/api/v1/admin/partnership/members/{$oldAgent->getKey()}/deactivate", [
            'replacement_sponsor_id' => $replacementDistributor->getKey(),
            'note' => 'Mitra mengakhiri kerja sama.',
        ])->assertOk()
            ->assertJsonPath('data.member.status.code', 0)
            ->assertJsonPath('data.moved_downlines', 1)
            ->assertJsonPath('data.transferred_reward_liabilities', 1);

        $this->assertDatabaseHas('member', [
            'member_id' => $oldAgent->getKey(),
            'member_status' => 0,
        ]);
        $this->assertDatabaseHas('member', [
            'member_id' => $reseller->getKey(),
            'member_parent_member_id' => $replacementDistributor->getKey(),
        ]);
        $this->assertDatabaseHas('member', [
            'member_id' => $nestedReseller->getKey(),
            'member_parent_member_id' => $reseller->getKey(),
        ]);
        $this->assertDatabaseHas('reward_point_monthly', [
            'reward_point_monthly_id' => $unpaidReward->getKey(),
            'reward_point_monthly_upline_id' => $replacementDistributor->getKey(),
            'reward_point_monthly_upline_level_id' => 1,
        ]);
        $this->assertDatabaseHas('reward_point_monthly', [
            'reward_point_monthly_id' => $paidReward->getKey(),
            'reward_point_monthly_upline_id' => $oldAgent->getKey(),
        ]);
        $this->assertDatabaseHas('member_history', [
            'member_history_member_id' => $oldAgent->getKey(),
            'member_history_action' => 'deactivate',
            'member_history_upline_member_id' => $replacementDistributor->getKey(),
        ]);
    }

    public function test_member_with_cancellable_transaction_can_be_deactivated_after_confirmation_and_stock_is_released(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $member = $this->createMember('MEM-105', 'Mitra Bertransaksi', 'agent', 1);
        $category = ProductCategory::query()->create([
            'product_category_name' => 'Produk Uji',
            'product_category_description' => 'Produk untuk pengujian penonaktifan mitra.',
            'product_category_is_active' => 1,
        ]);
        $product = Product::query()->create([
            'product_product_category_id' => $category->getKey(),
            'product_code' => 'PRD-DEACTIVATE',
            'product_name' => 'Produk Penonaktifan',
            'product_customer_price' => 100000,
            'product_is_publish' => 1,
            'product_is_active' => 1,
            'product_is_deleted' => 0,
        ]);
        MemberStock::query()->create([
            'member_stock_member_id' => $member->getKey(),
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 7,
            'member_stock_transfer_in' => 0,
            'member_stock_transfer_out' => 3,
        ]);
        $transaction = Trx::query()->create([
            'trx_code' => 'TRX-ONGOING-001',
            'trx_seller_type' => 'agent',
            'trx_seller_id' => $member->getKey(),
            'trx_buyer_type' => 'customer',
            'trx_buyer_id' => 1,
            'trx_type' => 'stock',
            'trx_total_price' => 100000,
            'trx_status' => 'waiting_payment',
            'trx_status_datetime' => now(),
            'trx_datetime' => now(),
        ]);
        $transaction->details()->create([
            'trx_detail_product_id' => $product->getKey(),
            'trx_detail_product_code' => $product->product_code,
            'trx_detail_product_name' => $product->product_name,
            'trx_detail_product_price' => 100000,
            'trx_detail_nett_price' => 100000,
            'trx_detail_qty' => 3,
        ]);

        $this->getJson("/api/v1/admin/partnership/members/{$member->getKey()}/deactivation-options")
            ->assertOk()
            ->assertJsonPath('data.can_deactivate', true)
            ->assertJsonPath('data.requires_transaction_cancellation', true)
            ->assertJsonPath('data.summary.active_transaction_count', 1)
            ->assertJsonPath('data.summary.cancellable_transaction_count', 1)
            ->assertJsonPath('data.summary.blocking_transaction_count', 0);

        $this->postJson("/api/v1/admin/partnership/members/{$member->getKey()}/deactivate")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');

        $this->postJson("/api/v1/admin/partnership/members/{$member->getKey()}/deactivate", [
            'cancel_active_transactions' => true,
        ])->assertOk()
            ->assertJsonPath('data.cancelled_transactions', 1)
            ->assertJsonPath('data.released_stock_orders', 1);

        $this->assertDatabaseHas('member', [
            'member_id' => $member->getKey(),
            'member_status' => 0,
        ]);
        $this->assertDatabaseHas('trx', [
            'trx_id' => $transaction->getKey(),
            'trx_status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => $member->getKey(),
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => 10,
            'member_stock_transfer_out' => 0,
        ]);
        $this->assertDatabaseHas('member_stock_log', [
            'member_stock_log_member_id' => $member->getKey(),
            'member_stock_log_product_id' => $product->getKey(),
            'member_stock_log_type' => 'in',
            'member_stock_log_quantity' => 3,
            'member_stock_log_note' => 'Pembatalan pesanan TRX-ONGOING-001',
        ]);
    }

    public function test_member_with_transaction_already_in_delivery_cannot_be_deactivated(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $member = $this->createMember('MEM-107', 'Mitra Dalam Pengiriman', 'agent', 1);
        Trx::query()->create([
            'trx_code' => 'TRX-SHIPPED-001',
            'trx_seller_type' => 'agent',
            'trx_seller_id' => $member->getKey(),
            'trx_buyer_type' => 'customer',
            'trx_buyer_id' => 1,
            'trx_type' => 'stock',
            'trx_total_price' => 100000,
            'trx_status' => 'shipped',
            'trx_status_datetime' => now(),
            'trx_datetime' => now(),
        ]);

        $this->getJson("/api/v1/admin/partnership/members/{$member->getKey()}/deactivation-options")
            ->assertOk()
            ->assertJsonPath('data.can_deactivate', false)
            ->assertJsonPath('data.summary.active_transaction_count', 1)
            ->assertJsonPath('data.summary.cancellable_transaction_count', 0)
            ->assertJsonPath('data.summary.blocking_transaction_count', 1);

        $this->postJson("/api/v1/admin/partnership/members/{$member->getKey()}/deactivate", [
            'cancel_active_transactions' => true,
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error');

        $this->assertDatabaseHas('member', [
            'member_id' => $member->getKey(),
            'member_status' => 1,
        ]);
    }

    public function test_administrator_can_reset_member_password_to_birth_date_and_queue_email(): void
    {
        Mail::fake();
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $member = $this->createMember('MEM-106', 'Reset Password', 'reseller', 1);
        $member->update(['member_birth_date' => '1994-05-17']);

        $this->postJson("/api/v1/admin/partnership/members/{$member->getKey()}/reset-password")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data.password');

        $account = MemberAccount::query()
            ->where('member_account_member_id', $member->getKey())
            ->firstOrFail();
        $this->assertTrue(Hash::check('17051994', $account->member_account_password));
        Mail::assertQueued(
            MemberPasswordResetByAdminMail::class,
            fn (MemberPasswordResetByAdminMail $mail): bool => $mail->hasTo($member->member_email)
                && $mail->password === '17051994'
                && $mail->memberCode === $member->member_code,
        );
    }

    private function createMember(string $code, string $name, string $level, int $status): Member
    {
        $this->createRegionReferences();
        $member = Member::query()->create([
            'member_code' => $code,
            'member_member_level_id' => match ($level) {
                'distributor' => 1,
                'agent' => 2,
                'reseller' => 3,
            },
            'member_name' => $name,
            'member_email' => strtolower($name).'@example.test',
            'member_mobilephone' => '08123456789',
            'member_join_datetime' => now(),
            'member_status' => $status,
        ]);

        if ($status !== 3) {
            $group = MemberGroup::query()->firstOrCreate(
                ['member_group_name' => 'Member'],
                ['member_group_description' => 'Member', 'member_group_is_active' => 1]
            );
            MemberAccount::query()->create([
                'member_account_member_id' => $member->member_id,
                'member_account_member_group_id' => $group->member_group_id,
                'member_account_username' => 'member.'.strtolower($code),
                'member_account_password' => Hash::make('Secret123'),
                'member_account_pin' => '',
            ]);
            MemberAddress::query()->create([
                'member_address_member_id' => $member->getKey(),
                'member_address_label' => 'Alamat Utama',
                'member_address_recipient' => $name,
                'member_address_phone' => '08123456789',
                'member_address_full' => 'Purwakarta',
                'member_address_province_id' => 9,
                'member_address_city_id' => 376,
                'member_address_district_id' => 5218,
                'member_address_subdistrict_id' => 60281,
                'member_address_country_id' => 1,
                'member_address_is_default' => 1,
            ]);
        }

        return $member;
    }

    private function createRegionReferences(): void
    {
        DB::table('ref_country')->updateOrInsert(
            ['country_id' => 1],
            [
                'country_iso_code' => 'ID',
                'country_phone_code' => '+62',
                'country_name' => 'Indonesia',
                'country_flag' => '',
                'country_is_active' => 1,
            ]
        );
        DB::table('ref_province')->updateOrInsert(
            ['province_id' => 9],
            ['province_name' => 'Jawa Barat']
        );
        DB::table('ref_city')->updateOrInsert(
            ['city_id' => 376],
            [
                'city_province_id' => 9,
                'city_name' => 'Purwakarta',
                'city_type' => 'Kabupaten',
                'city_is_active' => 1,
            ]
        );
        DB::table('ref_district')->updateOrInsert(
            ['district_id' => 5218],
            [
                'district_city_id' => 376,
                'district_name' => 'Purwakarta',
            ]
        );
        DB::table('ref_subdistrict')->updateOrInsert(
            ['subdistrict_id' => 60281],
            [
                'subdistrict_district_id' => 5218,
                'subdistrict_name' => 'Nagrikaler',
                'subdistrict_zip_code' => 41115,
            ]
        );
    }

    private function createAdministrator(): SiteAdministrator
    {
        $group = SiteAdministratorGroup::query()->create([
            'administrator_group_title' => 'Super Administrator',
            'administrator_group_type' => 'superuser',
            'administrator_group_is_active' => 1,
        ]);

        return SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->administrator_group_id,
            'administrator_username' => 'member.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Member Admin',
            'administrator_email' => 'member.admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }
}
