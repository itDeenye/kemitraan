<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Http\Resources\Api\V1\Admin\AdminReturnResource;
use App\Models\MemberRegistration;
use App\Models\MemberUpgradeQualified;
use App\Models\ReturnModel;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use App\Models\Trx;
use App\Services\Dashboard\AdminActionSummaryService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminActionBadgeWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_return_badge_only_counts_rows_with_an_admin_action(): void
    {
        $this->actingAs($this->administrator(), 'admin_api');
        $expected = 0;
        $detailActions = [];
        foreach (['pickup', 'courier_manual', null] as $method) {
            foreach ([
                'submitted', 'approved', 'rejected', 'completed', 'waiting_member_shipment',
                'return_in_transit', 'return_shipping_failed', 'received_by_company',
                'replacement_in_transit', 'replacement_shipping_failed',
            ] as $status) {
                $return = ReturnModel::query()->create([
                    'return_code' => 'RTR-'.count($detailActions),
                    'return_member_id' => 1,
                    'return_status' => $status,
                    'return_shipping_method' => $method,
                    'return_created_datetime' => now(),
                ]);
                $canReceive = $method !== 'pickup'
                    && in_array($status, ['waiting_member_shipment', 'return_in_transit'], true);
                $canAct = $canReceive || in_array($status, [
                    'submitted', 'return_shipping_failed', 'received_by_company', 'replacement_shipping_failed',
                ], true);
                $expected += (int) $canAct;
                $detailActions[$return->getKey()] = (new AdminReturnResource($return))
                    ->resolve(Request::create('/'))['actions'];
                $this->assertSame($canReceive, $detailActions[$return->getKey()]['can_receive']);
            }
        }
        $this->assertSame($expected, $this->badge('/admin/transactions/sales-returns'));

        $list = $this->getJson('/api/v1/admin/transactions/returns?limit=100')->assertOk();
        $this->assertCount(30, $list->json('data.results'));
        $active = 0;
        foreach ($list->json('data.results') as $row) {
            $this->assertSame($detailActions[$row['id']], $row['actions']);
            $active += (int) ($row['actions']['can_approve'] || $row['actions']['can_reject']
                || $row['actions']['can_receive'] || $row['actions']['can_ship_replacement']);
        }
        $this->assertSame($expected, $active);
        $seenHistory = false;
        foreach ($list->json('data.results') as $row) {
            $canAct = $row['actions']['can_approve'] || $row['actions']['can_reject']
                || $row['actions']['can_receive'] || $row['actions']['can_ship_replacement'];
            $this->assertFalse($seenHistory && $canAct, 'Actionable returns must precede history.');
            $seenHistory = $seenHistory || ! $canAct;
        }
    }

    public function test_shipping_badge_excludes_forwarded_orders_and_history(): void
    {
        $this->actingAs($this->administrator(), 'admin_api');
        foreach (['processing', 'reship_required', 'shipped', 'ready_to_pickup', 'received', 'completed'] as $status) {
            $this->order($status);
        }
        $forwarded = $this->order('processing');
        $this->order('processing', ['trx_parent_trx_id' => $forwarded->getKey(), 'trx_seller_type' => 'distributor']);
        $this->order('processing', ['trx_buyer_type' => 'agent']);

        $this->assertSame(2, $this->badge('/admin/inventory/shipping'));
        $list = $this->getJson('/api/v1/admin/inventory/shipments'
            .'?filter[buyer.type]=distributor&filter[seller.type]=warehouse&limit=100')->assertOk();
        $this->assertCount(7, $list->json('data.results'));
        $active = collect($list->json('data.results'))->filter(fn (array $row): bool => $row['actions']['can_ship'] || $row['actions']['can_reship']);
        $this->assertCount(2, $active);
        $this->assertSame($active->count(), $this->badge('/admin/inventory/shipping'));
    }

    public function test_shipment_list_is_not_duplicated_by_return_shipping_or_payment_rows(): void
    {
        $this->actingAs($this->administrator(), 'admin_api');
        $order = $this->order('processing');
        foreach (['trx', 'return_company', 'return_replacement'] as $type) {
            DB::table('shipping_courier_manual')->insert([
                'shipping_courier_manual_ref_type' => $type,
                'shipping_courier_manual_ref_id' => $order->getKey(),
                'shipping_courier_manual_origin_address' => 'Asal',
                'shipping_courier_manual_origin_subdistrict_id' => 0,
                'shipping_courier_manual_destination_address' => 'Tujuan',
                'shipping_courier_manual_destination_subdistrict_id' => 0,
                'shipping_courier_manual_price' => $type === 'trx' ? 5000 : 99000,
            ]);
            DB::table('shipping_pickup')->insert([
                'shipping_pickup_ref_type' => $type,
                'shipping_pickup_ref_id' => $order->getKey(),
                'shipping_pickup_seller_address' => 'Alamat pickup',
            ]);
        }
        foreach (['pending', 'submitted'] as $status) {
            DB::table('trx_payment_transfer')->insert([
                'payment_transfer_trx_id' => $order->getKey(),
                'payment_transfer_bill_amount' => 100000,
                'payment_transfer_bank_id' => 1,
                'payment_transfer_account_name' => 'Perusahaan',
                'payment_transfer_account_number' => '123456',
                'payment_transfer_amount' => 100000,
                'payment_transfer_datetime' => now(),
                'payment_transfer_approval_status' => $status,
                'payment_transfer_note' => '',
            ]);
        }

        $this->getJson('/api/v1/admin/inventory/shipments?filter[buyer.type]=distributor&filter[seller.type]=warehouse')
            ->assertOk()
            ->assertJsonPath('data.pagination.total_data', 1)
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', (int) $order->getKey())
            ->assertJsonPath('data.results.0.totals.shipping_cost', 5000);
        $this->assertSame(1, $this->badge('/admin/inventory/shipping'));
    }

    public function test_shipping_prioritizes_actions_before_pagination_and_preserves_history_and_sorting(): void
    {
        $this->actingAs($this->administrator(), 'admin_api');
        $first = $this->order('processing');
        $second = $this->order('reship_required');
        $history = $this->order('completed');
        $forwarded = $this->order('processing');
        $this->order('processing', ['trx_parent_trx_id' => $forwarded->getKey(), 'trx_seller_type' => 'distributor']);
        $url = '/api/v1/admin/inventory/shipments?filter[buyer.type]=distributor&filter[seller.type]=warehouse&limit=2';

        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.pagination.total_data', 4)
            ->assertJsonPath('data.results.0.id', $second->getKey())
            ->assertJsonPath('data.results.1.id', $first->getKey());
        $this->getJson($url.'&page=2')->assertOk()
            ->assertJsonPath('data.results.0.id', $forwarded->getKey())
            ->assertJsonPath('data.results.1.id', $history->getKey());
        $this->getJson($url.'&sort=id')->assertOk()
            ->assertJsonPath('data.results.0.id', $first->getKey())
            ->assertJsonPath('data.results.1.id', $second->getKey());
        $this->getJson($url.'&filter[status]=completed')->assertOk()
            ->assertJsonPath('data.pagination.total_data', 1)
            ->assertJsonPath('data.results.0.id', $history->getKey());
    }

    public function test_stock_screening_prioritizes_only_orders_with_approved_previous_payments(): void
    {
        $this->actingAs($this->administrator(), 'admin_api');
        $ready = $this->order('waiting_stock_screening');
        $parent = $this->order('waiting_payment', ['trx_seller_type' => 'distributor', 'trx_buyer_type' => 'agent']);
        $blocked = $this->order('waiting_stock_screening', [
            'trx_is_preorder' => 1, 'trx_parent_trx_id' => $parent->getKey(),
        ]);

        $this->getJson('/api/v1/admin/transactions/orders?filter[buyer.type]=distributor&filter[status]=waiting_stock_screening&limit=1')
            ->assertOk()->assertJsonPath('data.pagination.total_data', 2)
            ->assertJsonPath('data.results.0.id', $ready->getKey())
            ->assertJsonPath('data.results.0.actions.can_approve_stock_screening', true);
        $this->getJson('/api/v1/admin/transactions/orders?filter[buyer.type]=distributor&filter[status]=waiting_stock_screening&limit=1&page=2')
            ->assertOk()->assertJsonPath('data.results.0.id', $blocked->getKey())
            ->assertJsonPath('data.results.0.actions.can_approve_stock_screening', false);
    }

    public function test_stock_screening_badge_uses_the_same_buyer_scope_as_the_admin_page(): void
    {
        $this->order('waiting_stock_screening');
        $this->order('waiting_stock_screening', ['trx_buyer_type' => 'agent']);
        $this->order('waiting_stock_screening', ['trx_buyer_type' => 'reseller']);

        $this->assertSame(1, $this->badge('/admin/transactions/stock-screening'));
    }

    public function test_submitted_payments_requiring_verification_precede_payment_history(): void
    {
        $this->actingAs($this->administrator(), 'admin_api');
        $paymentIds = [];
        foreach ([['waiting_payment_approval', 'submitted'], ['completed', 'approved'], ['cancelled', 'submitted']] as [$trxStatus, $paymentStatus]) {
            $order = $this->order($trxStatus);
            $paymentIds[] = DB::table('trx_payment_transfer')->insertGetId([
                'payment_transfer_trx_id' => $order->getKey(),
                'payment_transfer_bill_amount' => 100000, 'payment_transfer_bank_id' => 1,
                'payment_transfer_account_name' => 'Perusahaan', 'payment_transfer_account_number' => '123456',
                'payment_transfer_amount' => 100000, 'payment_transfer_datetime' => now(),
                'payment_transfer_approval_status' => $paymentStatus, 'payment_transfer_note' => '',
            ]);
        }
        $this->getJson('/api/v1/admin/transactions/payments?limit=1')->assertOk()
            ->assertJsonPath('data.pagination.total_data', 3)
            ->assertJsonPath('data.results.0.id', $paymentIds[0]);
        $this->getJson('/api/v1/admin/transactions/payments?limit=1&page=2')->assertOk()
            ->assertJsonPath('data.results.0.id', $paymentIds[2]);
    }

    public function test_admin_registrations_prioritize_requested_records_and_preserve_distributor_review_scope(): void
    {
        $this->actingAs($this->administrator(), 'admin_api');
        $ids = [];
        foreach ([['requested', 'not_required'], ['approved', 'approved'], ['rejected', 'not_required'], ['requested', 'pending']] as [$status, $review]) {
            $ids[] = MemberRegistration::query()->create([
                'member_registration_member_level_id' => 1,
                'member_registration_name' => 'Applicant '.count($ids),
                'member_registration_username' => '', 'member_registration_password' => '',
                'member_registration_status' => $status,
                'member_registration_distributor_approval_status' => $review,
                'member_registration_datetime' => now(),
            ])->getKey();
        }
        $this->getJson('/api/v1/admin/partnership/registrations?limit=1')->assertOk()
            ->assertJsonPath('data.pagination.total_data', 3)
            ->assertJsonPath('data.results.0.id', $ids[0]);
        $this->getJson('/api/v1/admin/partnership/registrations?limit=1&page=2')->assertOk()
            ->assertJsonPath('data.results.0.id', $ids[2]);
    }

    public function test_requested_upgrades_precede_applied_and_scheduled_history(): void
    {
        $this->actingAs($this->administrator(), 'admin_api');
        $ids = [];
        foreach (['requested', 'scheduled', 'applied', 'rejected'] as $status) {
            $ids[] = MemberUpgradeQualified::query()->create([
                'member_upgrade_qualified_member_id' => count($ids) + 1,
                'member_upgrade_qualified_from_level_id' => 3, 'member_upgrade_qualified_to_level_id' => 2,
                'member_upgrade_qualified_from_year_month' => 2609, 'member_upgrade_qualified_to_year_month' => 2610,
                'member_upgrade_qualified_status' => $status,
                'member_upgrade_qualified_created_datetime' => now(),
            ])->getKey();
        }
        $this->getJson('/api/v1/admin/partnership/upgrades?limit=1')->assertOk()
            ->assertJsonPath('data.pagination.total_data', 4)
            ->assertJsonPath('data.results.0.id', $ids[0]);
        $this->getJson('/api/v1/admin/partnership/upgrades?limit=1&page=2')->assertOk()
            ->assertJsonPath('data.results.0.id', $ids[3]);
    }

    private function badge(string $route): int
    {
        return collect(app(AdminActionSummaryService::class)->summary()['menus'])
            ->firstWhere('route', $route)['count'];
    }

    public function test_order_deduplication_preserves_stock_screening_action_counts(): void
    {
        $this->actingAs($this->administrator(), 'admin_api');
        foreach ([['pending', 'approved'], ['approved', 'rejected']] as $statuses) {
            $parent = $this->order('processing', ['trx_seller_type' => 'distributor', 'trx_buyer_type' => 'agent']);
            $this->order('waiting_stock_screening', ['trx_is_preorder' => 1, 'trx_parent_trx_id' => $parent->getKey()]);
            foreach ($statuses as $status) {
                DB::table('trx_payment_transfer')->insert([
                    'payment_transfer_trx_id' => $parent->getKey(),
                    'payment_transfer_bill_amount' => 100000, 'payment_transfer_bank_id' => 1,
                    'payment_transfer_account_name' => 'Perusahaan', 'payment_transfer_account_number' => '123456',
                    'payment_transfer_amount' => 100000, 'payment_transfer_datetime' => now(),
                    'payment_transfer_approval_status' => $status, 'payment_transfer_note' => '',
                ]);
            }
        }
        $list = $this->getJson('/api/v1/admin/transactions/orders?filter[buyer.type]=distributor'
            .'&filter[status]=waiting_stock_screening&filter[can_approve_stock_screening]=1')
            ->assertOk()->assertJsonCount(1, 'data.results');
        $this->assertSame($list->json('data.pagination.total_data'), $this->badge('/admin/transactions/stock-screening'));
    }

    /** @param array<string, mixed> $attributes */
    private function order(string $status, array $attributes = []): Trx
    {
        return Trx::query()->create([
            'trx_code' => 'TRX-'.(Trx::query()->count() + 1),
            'trx_seller_type' => 'warehouse', 'trx_seller_id' => 1,
            'trx_buyer_type' => 'distributor', 'trx_buyer_id' => 1,
            'trx_type' => 'stock', 'trx_shipping_method' => 'courier_manual',
            'trx_total_price' => 100000, 'trx_grand_total_price' => 100000,
            'trx_grand_total_nett_price' => 100000, 'trx_bill_amount' => 100000,
            'trx_payment_method' => 'transfer',
            'trx_status' => $status, 'trx_status_datetime' => now(), 'trx_datetime' => now(),
            ...$attributes,
        ]);
    }

    private function administrator(): SiteAdministrator
    {
        $group = SiteAdministratorGroup::query()->create([
            'administrator_group_title' => 'Administrator',
            'administrator_group_type' => 'superuser',
            'administrator_group_is_active' => 1,
        ]);

        return SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->getKey(),
            'administrator_username' => 'badges.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Administrator', 'administrator_email' => 'badges@example.test',
            'administrator_is_active' => 1,
        ]);
    }
}
