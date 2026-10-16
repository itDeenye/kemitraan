<?php

namespace Tests\Feature\Api\V1\Member;

use App\Models\GoodsReceive;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\MemberLevel;
use App\Models\ShippingCourierExpress;
use App\Models\ShippingPickup;
use App\Models\Trx;
use App\Models\TrxPaymentTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberActionPriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_actions_are_prioritized_globally_before_pagination(): void
    {
        $account = $this->member();
        $payable = $this->purchase($account, 'waiting_payment');
        $cancellable = $this->purchase($account, 'waiting_stock_screening');
        $completed = $this->purchase($account, 'completed');
        $pending = $this->purchase($account, 'waiting_payment_approval');
        $this->actingAs($account, 'member_api');

        $first = $this->getJson('/api/v1/member/purchases/orders?limit=2')->assertOk()
            ->assertJsonPath('data.pagination.total_data', 4)
            ->assertJsonPath('data.results.0.actions.can_cancel', true)
            ->assertJsonPath('data.results.1.actions.can_upload_payment', true);
        $this->assertSame([$cancellable->getKey(), $payable->getKey()], array_column($first->json('data.results'), 'id'));
        $second = $this->getJson('/api/v1/member/purchases/orders?limit=2&page=2')->assertOk();
        $this->assertSame([$pending->getKey(), $completed->getKey()], array_column($second->json('data.results'), 'id'));

        $this->getJson('/api/v1/member/purchases/orders?limit=1&sort=-code')->assertOk()
            ->assertJsonPath('data.results.0.id', $cancellable->getKey());
    }

    public function test_purchase_payment_requires_previous_approval_and_keeps_hidden_preorders_hidden(): void
    {
        $account = $this->member();
        $previous = $this->purchase($account, 'waiting_payment_approval');
        $this->payment($previous, 'pending');
        $eligiblePrevious = $this->purchase($account, 'completed');
        $this->payment($eligiblePrevious, 'approved');
        $payable = $this->purchase($account, 'waiting_payment', [
            'trx_parent_trx_id' => $eligiblePrevious->getKey(), 'trx_is_preorder' => true,
        ]);
        $blocked = $this->purchase($account, 'waiting_payment', ['trx_parent_trx_id' => $previous->getKey()]);
        $hidden = $this->purchase($account, 'waiting_payment', [
            'trx_parent_trx_id' => $previous->getKey(), 'trx_is_preorder' => true,
        ]);
        $this->actingAs($account, 'member_api');

        $response = $this->getJson('/api/v1/member/purchases/orders')->assertOk()
            ->assertJsonPath('data.results.0.id', $payable->getKey())
            ->assertJsonPath('data.results.0.actions.can_upload_payment', true)
            ->assertJsonPath('data.results.1.id', $blocked->getKey())
            ->assertJsonPath('data.results.1.actions.can_upload_payment', false)
            ->assertJsonPath('data.results.1.actions.waiting_for_previous_approval', true);
        $this->assertNotContains($hidden->getKey(), array_column($response->json('data.results'), 'id'));
    }

    public function test_purchase_pickup_prioritizes_available_unused_codes_not_waiting_transit(): void
    {
        $account = $this->member();
        $ready = $this->purchase($account, 'ready_to_pickup', [
            'trx_is_preorder' => true, 'trx_preorder_fulfillment_mode' => 'upline_transit', 'trx_shipping_method' => 'pickup',
        ]);
        $this->pickup($ready, '12345');
        $waiting = $this->purchase($account, 'processing', [
            'trx_is_preorder' => true, 'trx_preorder_fulfillment_mode' => 'upline_transit', 'trx_shipping_method' => 'pickup',
        ]);
        $this->pickup($waiting, '54321');
        $missingPin = $this->purchase($account, 'processing', ['trx_shipping_method' => 'pickup']);
        $this->pickup($missingPin, '');
        $used = $this->purchase($account, 'processing', ['trx_shipping_method' => 'pickup']);
        $this->pickup($used, '98765', 'picked_up');
        $this->actingAs($account, 'member_api');

        $response = $this->getJson('/api/v1/member/purchases/orders?limit=2')->assertOk()
            ->assertJsonPath('data.results.0.id', $ready->getKey())
            ->assertJsonPath('data.results.0.actions.can_show_pickup_code', true)
            ->assertJsonPath('data.results.1.id', $used->getKey())
            ->assertJsonPath('data.results.1.actions.can_show_pickup_code', false);
        $remaining = $this->getJson('/api/v1/member/purchases/orders?limit=2&page=2')->assertOk()
            ->assertJsonPath('data.results.0.id', $missingPin->getKey())
            ->assertJsonPath('data.results.0.actions.can_show_pickup_code', false)
            ->assertJsonPath('data.results.1.id', $waiting->getKey())
            ->assertJsonPath('data.results.1.actions.can_show_pickup_code', false);
    }

    public function test_sales_actions_are_prioritized_globally_before_pagination(): void
    {
        $account = $this->member();
        $cancellable = $this->sale($account, 'waiting_payment');
        $verifiable = $this->sale($account, 'waiting_payment_approval');
        $shippable = $this->sale($account, 'processing');
        $completed = $this->sale($account, 'completed');
        $cashPending = $this->sale($account, 'waiting_payment_approval', ['trx_payment_method' => 'cash']);
        $this->actingAs($account, 'member_api');

        $first = $this->getJson('/api/v1/member/sales/orders?limit=2')->assertOk()
            ->assertJsonPath('data.pagination.total_data', 5)
            ->assertJsonPath('data.results.0.actions.can_ship', true)
            ->assertJsonPath('data.results.1.actions.can_verify_payment', true);
        $this->assertSame([$shippable->getKey(), $verifiable->getKey()], array_column($first->json('data.results'), 'id'));
        $second = $this->getJson('/api/v1/member/sales/orders?limit=2&page=2')->assertOk()
            ->assertJsonPath('data.results.0.actions.can_cancel', true);
        $this->assertSame([$cancellable->getKey(), $cashPending->getKey()], array_column($second->json('data.results'), 'id'));
        $this->getJson('/api/v1/member/sales/orders?limit=2&page=3')->assertOk()
            ->assertJsonPath('data.results.0.id', $completed->getKey());
    }

    public function test_sales_transit_waiting_for_delivery_follows_arrival_and_handover_actions(): void
    {
        $account = $this->member();
        $handover = $this->sale($account, 'ready_to_pickup', [
            'trx_is_preorder' => true, 'trx_preorder_fulfillment_mode' => 'upline_transit', 'trx_shipping_method' => 'pickup',
        ]);
        $arrival = $this->transitSale($account, 'finished_packages');
        $waiting = $this->transitSale($account, 'processed_packages');
        $stale = $this->transitSale($account, 'finished_packages');
        $terminal = $stale->children()->firstOrFail();
        $this->expressStatus($terminal->shippingExpress, 'returned_packages');
        $this->actingAs($account, 'member_api');

        $first = $this->getJson('/api/v1/member/sales/orders?limit=2')->assertOk()
            ->assertJsonPath('data.results.0.id', $arrival->getKey())
            ->assertJsonPath('data.results.0.actions.can_confirm_arrival', true)
            ->assertJsonPath('data.results.1.id', $handover->getKey())
            ->assertJsonPath('data.results.1.actions.can_handover_pickup', true);
        $second = $this->getJson('/api/v1/member/sales/orders?limit=2&page=2')->assertOk()
            ->assertJsonPath('data.results.0.actions.can_confirm_arrival', false)
            ->assertJsonPath('data.results.1.actions.can_ship', false);
        $this->assertSame([$stale->getKey(), $waiting->getKey()], array_column($second->json('data.results'), 'id'));
    }

    public function test_ready_receipts_precede_completed_undelivered_and_shipped_rows_before_pagination(): void
    {
        $account = $this->member();
        $manual = $this->purchase($account, 'received');
        $delivered = $this->purchase($account, 'received', ['trx_shipping_method' => 'courier_express']);
        $this->express($delivered, 'finished_packages');
        $preorder = $this->purchase($account, 'received', ['trx_is_preorder' => true]);
        $this->purchase($account, 'shipped', ['trx_is_preorder' => true, 'trx_parent_trx_id' => $preorder->getKey()]);
        $undelivered = $this->purchase($account, 'received', ['trx_shipping_method' => 'courier_express']);
        $this->express($undelivered, 'processed_packages');
        $completed = $this->purchase($account, 'completed');
        $this->receive($completed);
        $shipped = $this->purchase($account, 'shipped');
        $this->actingAs($account, 'member_api');

        $first = $this->getJson('/api/v1/member/purchases/goods-receipts?limit=2')->assertOk()
            ->assertJsonPath('data.pagination.total_data', 6)
            ->assertJsonPath('data.results.0.actions.can_receive', true)
            ->assertJsonPath('data.results.1.actions.can_receive', true);
        $this->assertSame([$preorder->getKey(), $delivered->getKey()], array_column($first->json('data.results'), 'purchase_order_id'));
        $second = $this->getJson('/api/v1/member/purchases/goods-receipts?limit=2&page=2')->assertOk();
        $this->assertSame([$manual->getKey(), $shipped->getKey()], array_column($second->json('data.results'), 'purchase_order_id'));
        $third = $this->getJson('/api/v1/member/purchases/goods-receipts?limit=2&page=3')->assertOk()
            ->assertJsonPath('data.results.0.actions.can_receive', false)
            ->assertJsonPath('data.results.1.actions.can_receive', false);
        $this->assertSame([$completed->getKey(), $undelivered->getKey()], array_column($third->json('data.results'), 'purchase_order_id'));
    }

    private function member(): MemberAccount
    {
        $level = MemberLevel::query()->firstOrCreate(['member_level_code' => 'DST'], [
            'member_level_name' => 'Distributor', 'member_level_description' => 'Distributor',
            'member_level_min_order' => 0, 'member_level_point_value' => 1,
            'member_level_sort_order' => 1, 'member_level_is_active' => 1,
        ]);
        $member = Member::query()->create([
            'member_code' => 'ACTION-MEMBER', 'member_name' => 'Action Member',
            'member_member_level_id' => $level->getKey(), 'member_status' => 1, 'member_join_datetime' => now(),
        ]);
        $group = MemberGroup::query()->create([
            'member_group_name' => 'Actions', 'member_group_description' => 'Actions', 'member_group_is_active' => 1,
        ]);

        return MemberAccount::query()->create([
            'member_account_member_id' => $member->getKey(), 'member_account_member_group_id' => $group->getKey(),
            'member_account_username' => 'actions', 'member_account_password' => 'test', 'member_account_pin' => '',
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function purchase(MemberAccount $account, string $status, array $attributes = []): Trx
    {
        return $this->transaction($status, array_replace([
            'trx_seller_type' => 'warehouse', 'trx_seller_id' => 99,
            'trx_buyer_type' => 'distributor', 'trx_buyer_id' => $account->member_account_member_id,
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function sale(MemberAccount $account, string $status, array $attributes = []): Trx
    {
        return $this->transaction($status, array_replace([
            'trx_seller_type' => 'distributor', 'trx_seller_id' => $account->member_account_member_id,
            'trx_buyer_type' => 'reseller', 'trx_buyer_id' => 999,
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function transaction(string $status, array $attributes): Trx
    {
        return Trx::query()->create(array_replace([
            'trx_code' => 'ACTION-'.Trx::query()->count(), 'trx_type' => 'stock',
            'trx_total_price' => 100000, 'trx_grand_total_price' => 100000, 'trx_grand_total_nett_price' => 100000,
            'trx_status' => $status, 'trx_status_datetime' => now(), 'trx_datetime' => now(),
            'trx_payment_method' => 'transfer', 'trx_shipping_method' => 'courier_manual',
        ], $attributes));
    }

    private function transitSale(MemberAccount $account, string $shippingStatus): Trx
    {
        $root = $this->sale($account, 'processing', [
            'trx_is_preorder' => true, 'trx_preorder_fulfillment_mode' => 'upline_transit', 'trx_shipping_method' => 'pickup',
        ]);
        $terminal = $this->purchase($account, 'received', [
            'trx_is_preorder' => true, 'trx_parent_trx_id' => $root->getKey(), 'trx_shipping_method' => 'courier_express',
        ]);
        $this->express($terminal, $shippingStatus);

        return $root;
    }

    private function payment(Trx $trx, string $status): void
    {
        TrxPaymentTransfer::query()->create([
            'payment_transfer_trx_id' => $trx->getKey(), 'payment_transfer_bill_amount' => 100000,
            'payment_transfer_amount' => 100000, 'payment_transfer_datetime' => now(),
            'payment_transfer_approval_status' => $status, 'payment_transfer_note' => '',
        ]);
    }

    private function pickup(Trx $trx, string $pin, string $status = 'pending'): void
    {
        $pickup = ShippingPickup::query()->create([
            'shipping_pickup_ref_type' => 'trx', 'shipping_pickup_ref_id' => $trx->getKey(),
            'shipping_pickup_pin' => $pin, 'shipping_pickup_seller_address' => 'Sender',
        ]);
        $pickup->statuses()->create([
            'shipping_pickup_status_ref_type' => 'trx', 'shipping_pickup_status_ref_id' => $trx->getKey(),
            'shipping_pickup_status_value' => $status, 'shipping_pickup_status_datetime' => now(),
        ]);
    }

    private function express(Trx $trx, string $status): void
    {
        $shipping = ShippingCourierExpress::query()->create([
            'shipping_courier_express_ref_type' => 'trx', 'shipping_courier_express_ref_id' => $trx->getKey(),
            'shipping_courier_express_origin_address' => 'Sender', 'shipping_courier_express_origin_subdistrict_id' => 1,
            'shipping_courier_express_destination_address' => 'Receiver', 'shipping_courier_express_destination_subdistrict_id' => 1,
        ]);
        $this->expressStatus($shipping, $status);
    }

    private function expressStatus(ShippingCourierExpress $shipping, string $status): void
    {
        $shipping->statuses()->create([
            'shipping_courier_express_status_ref_type' => 'trx',
            'shipping_courier_express_status_ref_id' => $shipping->shipping_courier_express_ref_id,
            'shipping_courier_express_status_value' => $status, 'shipping_courier_express_status_datetime' => now(),
        ]);
    }

    private function receive(Trx $trx): void
    {
        GoodsReceive::query()->create([
            'goods_receive_number' => 'GRN-'.$trx->getKey(), 'goods_receive_trx_id' => $trx->getKey(),
            'goods_receive_buyer_type' => $trx->trx_buyer_type, 'goods_receive_buyer_id' => $trx->trx_buyer_id,
            'goods_receive_seller_type' => $trx->trx_seller_type, 'goods_receive_seller_id' => $trx->trx_seller_id,
            'goods_receive_status' => 'completed', 'goods_receive_created_datetime' => now(),
        ]);
    }
}
