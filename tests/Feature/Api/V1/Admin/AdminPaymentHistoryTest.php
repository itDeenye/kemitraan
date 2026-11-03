<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminPaymentHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        foreach ([7 => ['Transaksi', '#', 0], 114 => ['Verifikasi Pembayaran', '/transactions/payment-verification', 7],
            166 => ['Riwayat Verifikasi Pembayaran', '/transactions/payment-verification-history', 7]] as $id => [$title, $link, $parent]) {
            DB::table('site_administrator_menu')->insert([
                'administrator_menu_id' => $id, 'administrator_menu_title' => $title,
                'administrator_menu_link' => $link, 'administrator_menu_par_id' => $parent,
            ]);
        }
        DB::table('warehouse')->insert(['warehouse_id' => 1, 'warehouse_name' => 'Perusahaan', 'warehouse_legal_name' => 'PT DNY']);
        DB::table('member')->insert([
            'member_id' => 1, 'member_code' => 'DST-001', 'member_name' => 'Distributor Uji', 'member_member_level_id' => 1,
            'member_join_datetime' => '2026-10-01 08:00:00',
        ]);
        DB::table('ref_bank')->insert(['bank_id' => 1, 'bank_code' => 'BCA', 'bank_name' => 'Bank Central Asia']);
        foreach ([1 => ['pending', 'warehouse', 'distributor'], 2 => ['submitted', 'warehouse', 'distributor'],
            3 => ['approved', 'warehouse', 'distributor'], 4 => ['rejected', 'warehouse', 'distributor'],
            5 => ['approved', 'warehouse', 'agent'], 6 => ['approved', 'distributor', 'distributor'],
            7 => ['approved', 'warehouse', 'distributor']] as $id => [$status, $seller, $buyer]) {
            DB::table('trx')->insert([
                'trx_id' => $id, 'trx_code' => 'PAYMENT-HISTORY-'.$id, 'trx_seller_type' => $seller,
                'trx_seller_id' => 1, 'trx_buyer_type' => $buyer, 'trx_buyer_id' => 1,
                'trx_type' => 'stock', 'trx_payment_method' => 'transfer', 'trx_shipping_method' => 'courier_manual',
                'trx_total_price' => 100000, 'trx_grand_total_price' => 100000,
                'trx_grand_total_nett_price' => 100000, 'trx_bill_amount' => 100000,
                'trx_status' => $id === 7 ? 'rejected' : ($status === 'submitted' ? 'waiting_payment_approval' : ($status === 'rejected' ? 'cancelled' : 'processing')),
                'trx_datetime' => '2026-10-30 08:00:00',
                'trx_status_datetime' => '2026-11-01 10:00:00',
            ]);
            DB::table('trx_payment_transfer')->insert([
                'payment_transfer_id' => $id, 'payment_transfer_trx_id' => $id,
                'payment_transfer_bank_id' => 1, 'payment_transfer_account_name' => 'Perusahaan',
                'payment_transfer_account_number' => '0012345678', 'payment_transfer_bill_amount' => 100000,
                'payment_transfer_amount' => 100000, 'payment_transfer_datetime' => '2026-10-30 09:00:00',
                'payment_transfer_approval_status' => $status,
                'payment_transfer_approval_datetime' => in_array($status, ['approved', 'rejected'], true) ? '2026-11-0'.($id === 4 ? '2' : '1').' 10:00:00' : null,
                'payment_transfer_note' => $status === 'rejected' ? 'Nominal tidak sesuai' : 'Sesuai',
            ]);
        }
    }

    public function test_history_requires_authentication_and_menu_access(): void
    {
        $this->getJson('/api/v1/admin/transactions/payment-history')->assertUnauthorized();
        $this->actingAs($this->administrator(false), 'admin_api');
        $this->getJson('/api/v1/admin/transactions/payment-history')->assertForbidden();
        $this->getJson('/api/v1/admin/transactions/payment-history/3')->assertForbidden();
    }

    public function test_history_only_lists_verified_company_payments_with_filters_and_pagination(): void
    {
        $this->actingAs($this->administrator(), 'admin_api');
        $url = '/api/v1/admin/transactions/payment-history';
        $this->getJson($url.'?sort=id')->assertOk()->assertJsonCount(3, 'data.results')
            ->assertJsonPath('data.results.0.id', 3)->assertJsonPath('data.results.0.status', 'approved')
            ->assertJsonPath('data.results.1.id', 4)->assertJsonPath('data.results.1.status', 'rejected')
            ->assertJsonPath('data.results.1.transaction.status', 'cancelled')
            ->assertJsonPath('data.results.1.payment.note', 'Nominal tidak sesuai')
            ->assertJsonPath('data.results.2.id', 7)->assertJsonPath('data.results.2.transaction.status', 'rejected');
        $this->getJson($url.'?filter[status]=approved')->assertOk()->assertJsonCount(2, 'data.results');
        $this->getJson($url.'?filter[status]=submitted')->assertUnprocessable();
        $this->getJson($url.'?filter[status]=rejected')->assertOk()->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', 4);
        $this->getJson($url.'?filter[transaction.status][eq]=rejected')->assertOk()->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', 7);
        $this->getJson($url.'?filter[seller.type]=distributor')->assertOk()->assertJsonCount(0, 'data.results');
        $this->getJson($url.'?filter[buyer.type]=agent')->assertOk()->assertJsonCount(0, 'data.results');
        $this->getJson($url.'?search=HISTORY-3')->assertOk()->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', 3);
        $this->getJson($url.'?search=HISTORY-4')->assertOk()->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', 4);
        $this->getJson($url.'?limit=1&sort=id&page=2')->assertOk()->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', 4)->assertJsonPath('data.pagination.total_data', 3);
        $this->getJson($url.'?pagination=false')->assertOk()->assertJsonCount(3, 'data.results')->assertJsonMissingPath('data.pagination');
        $this->getJson($url.'?date_from=2026-11-01&date_to=2026-11-01')->assertOk()
            ->assertJsonCount(2, 'data.results');
        Http::assertNothingSent();
    }

    public function test_history_detail_is_read_only_and_excludes_unverified_or_other_sellers(): void
    {
        $this->actingAs($this->administrator(), 'admin_api');
        $before = DB::table('trx_payment_transfer')->orderBy('payment_transfer_id')->get()->toJson();
        $this->getJson('/api/v1/admin/transactions/payment-history/3')->assertOk()
            ->assertJsonPath('data.payment.id', 3)->assertJsonPath('data.payment.status', 'approved')
            ->assertJsonPath('data.payment.bank.account_number', '0012345678')
            ->assertJsonPath('data.payment.verified_at', '2026-11-01T10:00:00+07:00');
        $this->getJson('/api/v1/admin/transactions/payment-history/4')->assertOk()
            ->assertJsonPath('data.payment.status', 'rejected')
            ->assertJsonPath('data.payment.note', 'Nominal tidak sesuai');
        $this->getJson('/api/v1/admin/transactions/payment-history/7')->assertOk()
            ->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.payment.status', 'approved');
        foreach ([1, 2, 5, 6] as $id) {
            $this->getJson('/api/v1/admin/transactions/payment-history/'.$id)->assertNotFound();
        }
        $this->postJson('/api/v1/admin/transactions/payment-history/3')->assertStatus(405);
        $this->postJson('/api/v1/admin/transactions/payments/2/approve')->assertForbidden();
        $this->postJson('/api/v1/admin/transactions/payments/2/reject')->assertForbidden();
        $this->assertSame($before, DB::table('trx_payment_transfer')->orderBy('payment_transfer_id')->get()->toJson());
        Http::assertNothingSent();
    }

    public function test_history_menu_migration_is_idempotent_and_only_grants_read_access_to_finance(): void
    {
        $finance = $this->administrator(false);
        DB::table('site_administrator_menu')->where('administrator_menu_id', 166)->delete();
        $migration = require database_path('migrations/2026_11_02_152645_add_payment_verification_history_admin_menu.php');
        $migration->up();
        $migration->up();
        $this->assertSame(1, DB::table('site_administrator_menu')->where('administrator_menu_link', '/transactions/payment-verification-history')->count());
        $this->assertTrue($finance->canAccessMenu('/transactions/payment-verification-history'));
        $this->assertFalse($finance->canAccessMenu('/transactions/payment-verification'));
        $migration->down();
        $this->assertFalse($finance->canAccessMenu('/transactions/payment-verification-history'));
    }

    private function administrator(bool $historyAccess = true): SiteAdministrator
    {
        $group = SiteAdministratorGroup::query()->create([
            'administrator_group_title' => 'Finance', 'administrator_group_type' => 'administrator', 'administrator_group_is_active' => 1,
        ]);
        if ($historyAccess) {
            $group->menus()->attach(166);
        }

        return SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->getKey(), 'administrator_username' => 'finance',
            'administrator_password' => Hash::make('Secret123'), 'administrator_name' => 'Finance', 'administrator_is_active' => 1,
        ]);
    }
}
