<?php

namespace Tests\Feature\Api\V1\Member;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\MemberLevel;
use App\Models\ShippingCourierExpress;
use App\Models\ShippingCourierExpressStatus;
use App\Models\ShippingCourierManual;
use App\Models\ShippingPickup;
use App\Models\Trx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreorderPickupReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_stc_transit_cannot_be_confirmed_before_delivery(): void
    {
        [$root, $terminal, $seller] = $this->transit();
        $this->actingAs($seller, 'member_api');
        $this->getJson("/api/v1/member/sales/orders/{$root->getKey()}")
            ->assertOk()->assertJsonPath('data.actions.can_confirm_arrival', false);
        $this->getJson('/api/v1/member/sales/orders')->assertOk()
            ->assertJsonPath('data.results.0.actions.can_confirm_arrival', false);
        $this->getJson('/api/v1/member/sales/orders?filter[action]=ship')->assertOk()
            ->assertJsonCount(0, 'data.results');
        $this->getJson('/api/v1/member/sales/orders/summary')->assertOk()
            ->assertJsonPath('data.action_required', 0);
        $this->postJson("/api/v1/member/sales/orders/{$root->getKey()}/confirm-arrival")
            ->assertUnprocessable();
        $this->assertSame('processing', $root->refresh()->trx_status);
        $this->assertSame('shipped', $terminal->refresh()->trx_status);
    }

    public function test_finished_stc_transit_enables_arrival_and_unused_pickup_badge(): void
    {
        [$root, $terminal, $seller, $buyer] = $this->transit();
        $this->actingAs($buyer, 'member_api');
        $this->getJson('/api/v1/member/purchases/orders/summary')->assertOk()
            ->assertJsonPath('data.purchases.action_required', 0)
            ->assertJsonPath('data.goods_receipts.ready_to_receive', 0);
        $this->postJson('/api/v1/callbacks/stc/shipping', [
            'method' => 'finished_packages',
            'data' => [['order_id' => 'STC-READINESS', 'awb' => 'AWB-READINESS', 'finished_at' => now()->toDateTimeString()]],
        ])->assertOk();
        $this->assertSame('received', $terminal->refresh()->trx_status);
        $this->assertSame('processing', $root->refresh()->trx_status);
        $this->actingAs($seller, 'member_api');
        $this->getJson("/api/v1/member/sales/orders/{$root->getKey()}")->assertOk()
            ->assertJsonPath('data.actions.can_confirm_arrival', true);
        $this->getJson('/api/v1/member/sales/orders?filter[action]=ship')->assertOk()
            ->assertJsonCount(1, 'data.results');
        $this->getJson('/api/v1/member/sales/orders/summary')->assertOk()
            ->assertJsonPath('data.action_required', 1);
        $this->postJson("/api/v1/member/sales/orders/{$root->getKey()}/confirm-arrival")
            ->assertOk()->assertJsonPath('data.status.code', 'ready_to_pickup');
        $this->actingAs($buyer, 'member_api');
        $this->getJson('/api/v1/member/purchases/orders/summary')->assertOk()
            ->assertJsonPath('data.purchases.action_required', 1)
            ->assertJsonPath('data.goods_receipts.ready_to_receive', 0);
        $this->actingAs($seller, 'member_api');
        $this->postJson("/api/v1/member/sales/orders/{$root->getKey()}/ship", ['pickup_pin' => '12345'])
            ->assertOk()->assertJsonPath('data.status.code', 'received');
        $this->actingAs($buyer, 'member_api');
        $this->getJson('/api/v1/member/purchases/orders/summary')->assertOk()
            ->assertJsonPath('data.purchases.action_required', 0)
            ->assertJsonPath('data.goods_receipts.ready_to_receive', 1);
        $this->assertDatabaseCount('member_stock', 0);
    }

    public function test_manual_transit_can_confirm_physical_arrival_after_shipping(): void
    {
        [$root, , $seller] = $this->transit('courier_manual');
        $this->actingAs($seller, 'member_api');
        $this->getJson("/api/v1/member/sales/orders/{$root->getKey()}")->assertOk()
            ->assertJsonPath('data.actions.can_confirm_arrival', true);
        $this->getJson('/api/v1/member/sales/orders?filter[action]=ship')->assertOk()
            ->assertJsonCount(1, 'data.results');
        $this->postJson("/api/v1/member/sales/orders/{$root->getKey()}/confirm-arrival")
            ->assertOk()->assertJsonPath('data.status.code', 'ready_to_pickup');
        $this->assertDatabaseCount('member_stock', 0);
    }

    public function test_latest_non_delivered_stc_status_blocks_stale_received_terminal(): void
    {
        [$root, $terminal, $seller] = $this->transit();
        $terminal->update(['trx_status' => 'received']);
        $shipping = $terminal->shippingExpress;
        $this->expressStatus($shipping, 'finished_packages');
        $this->expressStatus($shipping, 'returned_packages');
        $this->actingAs($seller, 'member_api');
        $this->getJson("/api/v1/member/sales/orders/{$root->getKey()}")->assertOk()
            ->assertJsonPath('data.actions.can_confirm_arrival', false);
        $this->getJson('/api/v1/member/sales/orders?filter[action]=ship')->assertOk()
            ->assertJsonCount(0, 'data.results');
        $this->postJson("/api/v1/member/sales/orders/{$root->getKey()}/confirm-arrival")->assertUnprocessable();
    }

    public function test_regular_pickup_badge_uses_latest_unused_code_and_ignores_children(): void
    {
        [$root, , , $buyer] = $this->transit();
        $root->update(['trx_is_preorder' => false, 'trx_preorder_fulfillment_mode' => 'direct']);
        $child = $this->transaction($root->trx_seller_id, $buyer->member_account_member_id, 'pickup');
        $child->update(['trx_is_preorder' => false, 'trx_preorder_fulfillment_mode' => 'direct',
            'trx_parent_trx_id' => $root->getKey()]);
        ShippingPickup::query()->create(['shipping_pickup_ref_type' => 'trx',
            'shipping_pickup_ref_id' => $child->getKey(), 'shipping_pickup_pin' => '54321',
            'shipping_pickup_seller_address' => 'Agent address']);
        $this->actingAs($buyer, 'member_api');
        $this->getJson('/api/v1/member/purchases/orders/summary')->assertOk()
            ->assertJsonPath('data.purchases.action_required', 1);
        $pickup = $root->shippingPickup;
        $pickup->update(['shipping_pickup_pin' => null]);
        $this->getJson('/api/v1/member/purchases/orders/summary')->assertOk()
            ->assertJsonPath('data.purchases.action_required', 0);
        $pickup->update(['shipping_pickup_pin' => '12345']);
        $pickup->statuses()->create([
            'shipping_pickup_status_ref_type' => 'trx', 'shipping_pickup_status_ref_id' => $root->getKey(),
            'shipping_pickup_status_value' => 'picked_up', 'shipping_pickup_status_datetime' => now(),
        ]);
        $this->getJson('/api/v1/member/purchases/orders/summary')->assertOk()
            ->assertJsonPath('data.purchases.action_required', 0);
        $this->actingAs($this->member('DST', 'Unrelated'), 'member_api');
        $this->getJson('/api/v1/member/purchases/orders/summary')->assertOk()
            ->assertJsonPath('data.purchases.action_required', 0);
        $this->postJson("/api/v1/member/sales/orders/{$root->getKey()}/confirm-arrival")->assertNotFound();
    }

    /** @return array{Trx, Trx, MemberAccount, MemberAccount} */
    private function transit(string $method = 'courier_express'): array
    {
        $seller = $this->member('AGT', 'Agent');
        $buyer = $this->member('RSL', 'Reseller');
        $root = $this->transaction($seller->member_account_member_id, $buyer->member_account_member_id, 'pickup');
        $pickup = ShippingPickup::query()->create([
            'shipping_pickup_ref_type' => 'trx', 'shipping_pickup_ref_id' => $root->getKey(),
            'shipping_pickup_pin' => '12345', 'shipping_pickup_seller_address' => 'Agent address',
        ]);
        $pickup->statuses()->create([
            'shipping_pickup_status_ref_type' => 'trx', 'shipping_pickup_status_ref_id' => $root->getKey(),
            'shipping_pickup_status_value' => 'pending', 'shipping_pickup_status_datetime' => now(),
        ]);
        $terminal = $this->transaction(99, $seller->member_account_member_id, $method);
        $terminal->update(['trx_seller_type' => $method === 'courier_manual' ? 'distributor' : 'warehouse',
            'trx_buyer_type' => 'agent', 'trx_parent_trx_id' => $root->getKey(), 'trx_status' => 'shipped']);
        if ($method === 'courier_manual') {
            ShippingCourierManual::query()->create(['shipping_courier_manual_ref_type' => 'trx', 'shipping_courier_manual_ref_id' => $terminal->getKey(),
                'shipping_courier_manual_origin_address' => 'Sender', 'shipping_courier_manual_origin_subdistrict_id' => 1,
                'shipping_courier_manual_destination_address' => 'Agent', 'shipping_courier_manual_destination_subdistrict_id' => 1]);
        } else {
            $shipping = ShippingCourierExpress::query()->create([
                'shipping_courier_express_ref_type' => 'trx', 'shipping_courier_express_ref_id' => $terminal->getKey(),
                'shipping_courier_express_order_id' => 'STC-READINESS', 'shipping_courier_express_awb' => 'AWB-READINESS',
                'shipping_courier_express_origin_address' => 'Sender', 'shipping_courier_express_origin_subdistrict_id' => 1,
                'shipping_courier_express_destination_address' => 'Agent', 'shipping_courier_express_destination_subdistrict_id' => 1,
            ]);
            $this->expressStatus($shipping, 'processed_packages');
        }

        return [$root, $terminal, $seller, $buyer];
    }

    private function expressStatus(ShippingCourierExpress $shipping, string $value): void
    {
        ShippingCourierExpressStatus::query()->create([
            'shipping_courier_express_status_shipping_courier_express_id' => $shipping->getKey(),
            'shipping_courier_express_status_ref_type' => 'trx',
            'shipping_courier_express_status_ref_id' => $shipping->shipping_courier_express_ref_id,
            'shipping_courier_express_status_value' => $value,
            'shipping_courier_express_status_datetime' => now(),
        ]);
    }

    private function transaction(int $seller, int $buyer, string $method): Trx
    {
        return Trx::query()->create([
            'trx_code' => 'READINESS-'.Trx::query()->count(), 'trx_seller_type' => 'agent', 'trx_seller_id' => $seller,
            'trx_buyer_type' => 'reseller', 'trx_buyer_id' => $buyer, 'trx_type' => 'stock',
            'trx_total_price' => 100000, 'trx_grand_total_price' => 100000, 'trx_grand_total_nett_price' => 100000,
            'trx_is_preorder' => true, 'trx_preorder_fulfillment_mode' => 'upline_transit',
            'trx_status' => 'processing', 'trx_shipping_method' => $method, 'trx_payment_method' => 'transfer',
            'trx_status_datetime' => now(), 'trx_datetime' => now(),
        ]);
    }

    private function member(string $code, string $name): MemberAccount
    {
        $level = MemberLevel::query()->firstOrCreate(['member_level_code' => $code], ['member_level_name' => $name,
            'member_level_description' => $name, 'member_level_min_order' => 0, 'member_level_point_value' => 1,
            'member_level_sort_order' => 1, 'member_level_is_active' => 1]);
        $member = Member::query()->create(['member_code' => $code, 'member_name' => $name,
            'member_member_level_id' => $level->getKey(), 'member_status' => 1, 'member_join_datetime' => now()]);
        $group = MemberGroup::query()->firstOrCreate(['member_group_name' => 'Readiness'],
            ['member_group_description' => 'Readiness', 'member_group_is_active' => 1]);

        return MemberAccount::query()->create(['member_account_member_id' => $member->getKey(),
            'member_account_member_group_id' => $group->getKey(), 'member_account_username' => $code,
            'member_account_password' => 'test', 'member_account_pin' => '']);
    }
}
