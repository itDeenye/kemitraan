<?php

namespace Tests\Feature\Api\V1\Member;

use App\Mail\PaymentRejectedMail;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberAddress;
use App\Models\MemberBankAccount;
use App\Models\MemberGroup;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use App\Models\Trx;
use App\Models\TrxPaymentTransfer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PreorderChainWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rejected_terminal_member_payment_cancels_whole_preorder_chain_and_releases_stock(): void
    {
        Mail::fake();
        [$distributor, $distributorAccount] = $this->createMember(1, 1, 0, 'DST', 'Distributor');
        [$agent, $agentAccount] = $this->createMember(2, 2, 1, 'AGT', 'Agent');
        [$reseller, $resellerAccount] = $this->createMember(3, 3, 2, 'RSL', 'Reseller');
        $product = $this->createReferences([$distributor, $agent, $reseller]);
        DB::table('member_stock')
            ->where('member_stock_member_id', $distributor->getKey())
            ->where('member_stock_product_id', $product->getKey())
            ->update(['member_stock_balance' => 5]);

        $this->actingAs($resellerAccount, 'member_api');
        $root = $this->postJson('/api/v1/member/purchases/orders', [
            'bank_account_id' => $this->bankAccountId($agent),
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 2]],
        ])->assertOk();
        $rootId = (int) $root->json('data.id');
        $terminal = Trx::query()->where('trx_parent_trx_id', $rootId)->sole();

        $this->submitPayment($resellerAccount, $rootId, 'rejected-terminal-root');
        $this->verifyPayment($agentAccount, $rootId);
        $this->submitPayment($agentAccount, $terminal->getKey(), 'rejected-terminal-agent');

        $this->actingAs($distributorAccount, 'member_api');
        $this->postJson("/api/v1/member/sales/orders/{$terminal->getKey()}/payment/reject", [
            'note' => 'Bukti tidak sesuai',
        ])->assertOk()
            ->assertJsonPath('data.status.code', 'cancelled');

        $this->assertSame(2, Trx::query()->where('trx_status', 'cancelled')->count());
        $this->assertStock($distributor, $product, 5, 0, 0);
        $this->assertStock($reseller, $product, 0, 0, 0);
        $this->assertDatabaseHas('trx_payment_transfer', [
            'payment_transfer_trx_id' => $terminal->getKey(),
            'payment_transfer_approval_status' => 'rejected',
        ]);
        $this->assertDatabaseCount('member_point_transaction', 0);
        $this->assertSame(1, DB::table('member_stock_log')
            ->where('member_stock_log_note', 'Pembatalan pesanan '.$root->json('data.code'))
            ->count());
        Mail::assertSent(PaymentRejectedMail::class, fn (PaymentRejectedMail $mail): bool => $mail->orderCancelled);
        $this->actingAs($agentAccount, 'member_api');
        $this->getJson("/api/v1/member/purchases/orders/{$terminal->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_upload_payment', false);
    }

    public function test_rejected_intermediate_preorder_payment_keeps_stock_reserved_for_resubmission(): void
    {
        Mail::fake();
        [$distributor] = $this->createMember(1, 1, 0, 'DST', 'Distributor');
        [$agent, $agentAccount] = $this->createMember(2, 2, 1, 'AGT', 'Agent');
        [$reseller, $resellerAccount] = $this->createMember(3, 3, 2, 'RSL', 'Reseller');
        $product = $this->createReferences([$distributor, $agent, $reseller]);
        DB::table('member_stock')
            ->where('member_stock_member_id', $distributor->getKey())
            ->where('member_stock_product_id', $product->getKey())
            ->update(['member_stock_balance' => 5]);

        $this->actingAs($resellerAccount, 'member_api');
        $root = $this->postJson('/api/v1/member/purchases/orders', [
            'bank_account_id' => $this->bankAccountId($agent),
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 2]],
        ])->assertOk();
        $rootId = (int) $root->json('data.id');
        $this->submitPayment($resellerAccount, $rootId, 'rejected-intermediate');

        $this->actingAs($agentAccount, 'member_api');
        $this->postJson("/api/v1/member/sales/orders/{$rootId}/payment/reject", [
            'note' => 'Bukti kurang jelas',
        ])->assertOk()
            ->assertJsonPath('data.status.code', 'waiting_payment');

        $this->assertSame(0, Trx::query()->where('trx_status', 'cancelled')->count());
        $this->assertStock($distributor, $product, 3, 0, 2);
        $this->assertStock($reseller, $product, 0, 2, 0);
        Mail::assertSent(PaymentRejectedMail::class, fn (PaymentRejectedMail $mail): bool => ! $mail->orderCancelled);
        $this->submitPayment($resellerAccount, $rootId, 'resubmitted-intermediate');
        $this->assertDatabaseCount('trx_payment_transfer', 2);
        $this->assertDatabaseHas('trx_payment_transfer', [
            'payment_transfer_trx_id' => $rootId,
            'payment_transfer_approval_status' => 'submitted',
        ]);
        $this->assertStock($distributor, $product, 3, 0, 2);
    }

    public function test_rejected_terminal_warehouse_payment_cancels_preorder_chain_and_releases_stock(): void
    {
        Mail::fake();
        [$distributor, $distributorAccount] = $this->createMember(1, 1, 0, 'DST', 'Distributor');
        [$agent, $agentAccount] = $this->createMember(2, 2, 1, 'AGT', 'Agent');
        $product = $this->createReferences([$distributor, $agent]);

        $this->actingAs($agentAccount, 'member_api');
        $root = $this->postJson('/api/v1/member/purchases/orders', [
            'bank_account_id' => $this->bankAccountId($distributor),
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 2]],
        ])->assertOk();
        $rootId = (int) $root->json('data.id');
        $terminal = Trx::query()->where('trx_parent_trx_id', $rootId)->sole();
        $this->submitPayment($agentAccount, $rootId, 'rejected-warehouse-root');
        $this->verifyPayment($distributorAccount, $rootId);

        $administrator = $this->createAdministrator();
        $this->actingAs($administrator, 'admin_api');
        $this->postJson("/api/v1/admin/transactions/orders/{$terminal->getKey()}/stock-screening/approve")
            ->assertOk();
        $this->submitPayment($distributorAccount, $terminal->getKey(), 'rejected-warehouse-terminal');
        $payment = TrxPaymentTransfer::query()->where('payment_transfer_trx_id', $terminal->getKey())->sole();

        $this->actingAs($administrator, 'admin_api');
        $this->postJson("/api/v1/admin/transactions/payments/{$payment->getKey()}/reject", [
            'note' => 'Bukti tidak sesuai',
        ])->assertOk()
            ->assertJsonPath('message', 'Pembayaran ditolak. Seluruh rangkaian PO dibatalkan dan alokasi stok dilepas.')
            ->assertJsonPath('data.payment.status', 'rejected');

        $this->assertSame(2, Trx::query()->where('trx_status', 'cancelled')->count());
        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 100,
            'warehouse_stock_transfer_out' => 0,
        ]);
        $this->assertStock($agent, $product, 0, 0, 0);
        Mail::assertSent(PaymentRejectedMail::class, fn (PaymentRejectedMail $mail): bool => $mail->orderCancelled);
        $this->actingAs($distributorAccount, 'member_api');
        $this->getJson("/api/v1/member/purchases/orders/{$terminal->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_upload_payment', false);
    }

    public function test_rejected_terminal_stock_screening_cancels_whole_preorder_chain(): void
    {
        [$distributor, $distributorAccount] = $this->createMember(1, 1, 0, 'DST', 'Distributor');
        [$agent, $agentAccount] = $this->createMember(2, 2, 1, 'AGT', 'Agent');
        $product = $this->createReferences([$distributor, $agent]);

        $this->actingAs($agentAccount, 'member_api');
        $root = $this->postJson('/api/v1/member/purchases/orders', [
            'bank_account_id' => $this->bankAccountId($distributor),
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 2]],
        ])->assertOk();
        $rootId = (int) $root->json('data.id');
        $terminal = Trx::query()->where('trx_parent_trx_id', $rootId)->sole();

        $this->submitPayment($agentAccount, $rootId, 'screening-rejected-root');
        $this->verifyPayment($distributorAccount, $rootId);
        $this->assertDatabaseCount('member_point_transaction', 0);

        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->postJson("/api/v1/admin/transactions/orders/{$terminal->getKey()}/stock-screening/reject", [
            'note' => 'Stok tidak dapat dipenuhi',
        ])->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath(
                'message',
                'Screening stok ditolak. Pesanan beserta seluruh rangkaian terkait dibatalkan dan reservasi stok perusahaan telah dilepas.',
            );

        $this->assertSame(2, Trx::query()->where('trx_status', 'cancelled')->count());
        $this->assertDatabaseCount('member_point_transaction', 0);
        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 100,
            'warehouse_stock_transfer_out' => 0,
        ]);
        $this->assertStock($agent, $product, 0, 0, 0);
    }

    public function test_reseller_can_purchase_directly_from_agent_that_has_upgraded_to_distributor(): void
    {
        [$upgradedDistributor] = $this->createMember(1, 1, 0, 'DST', 'Upgraded Distributor');
        [$reseller, $resellerAccount] = $this->createMember(2, 3, 1, 'RSL', 'Reseller');
        $product = $this->createReferences([$upgradedDistributor, $reseller]);

        $this->actingAs($resellerAccount, 'member_api');
        $this->postJson('/api/v1/member/purchases/orders', [
            'bank_account_id' => $this->bankAccountId($upgradedDistributor),
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 2]],
        ])->assertOk()
            ->assertJsonPath('data.buyer.id', $reseller->getKey())
            ->assertJsonPath('data.seller.type', 'distributor')
            ->assertJsonPath('data.seller.id', $upgradedDistributor->getKey());
    }

    public function test_pickup_preorder_chain_copies_shipping_to_the_next_seller(): void
    {
        [$distributor, $distributorAccount] = $this->createMember(1, 1, 0, 'DST', 'Distributor');
        [$agent, $agentAccount] = $this->createMember(2, 2, 1, 'AGT', 'Agent');
        [$reseller, $resellerAccount] = $this->createMember(3, 3, 2, 'RSL', 'Reseller');
        $product = $this->createReferences([$distributor, $agent, $reseller]);

        $this->actingAs($resellerAccount, 'member_api');
        $this->getJson('/api/v1/member/purchases/options?'.http_build_query([
            'items' => [['product_id' => $product->getKey(), 'quantity' => 2]],
        ]))->assertOk()
            ->assertJsonPath('data.seller.name', $agent->member_name)
            ->assertJsonPath('data.seller.origin.name', 'Warehouse Utama DNY')
            ->assertJsonPath('data.seller.origin.type', 'warehouse');
        $root = $this->postJson('/api/v1/member/purchases/orders', [
            'bank_account_id' => $this->bankAccountId($agent),
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 2]],
        ])->assertOk();
        $rootId = (int) $root->json('data.id');
        $rootPickupPin = (string) DB::table('shipping_pickup')
            ->where('shipping_pickup_ref_type', 'trx')
            ->where('shipping_pickup_ref_id', $rootId)
            ->value('shipping_pickup_pin');
        $agentOrder = Trx::query()->where('trx_parent_trx_id', $rootId)->sole();
        $companyOrder = Trx::query()->where('trx_parent_trx_id', $agentOrder->getKey())->sole();

        $this->getJson("/api/v1/member/purchases/orders/{$rootId}")
            ->assertOk()
            ->assertJsonPath('data.shipping.code', $rootPickupPin);
        $this->actingAs($agentAccount, 'member_api');
        $this->getJson("/api/v1/member/purchases/orders/{$agentOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.shipping.code', null)
            ->assertJsonPath('data.actions.can_show_pickup_code', false);
        $this->actingAs($distributorAccount, 'member_api');
        $this->getJson("/api/v1/member/purchases/orders/{$companyOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.shipping.code', null)
            ->assertJsonPath('data.actions.can_show_pickup_code', false);
        $this->actingAs($resellerAccount, 'member_api');

        $this->assertSame('pickup', $companyOrder->trx_shipping_method);
        $this->assertDatabaseMissing('shipping_courier_express', [
            'shipping_courier_express_ref_type' => 'trx',
            'shipping_courier_express_ref_id' => $companyOrder->getKey(),
        ]);
        $this->assertSame(
            [$rootPickupPin],
            DB::table('shipping_pickup')
                ->where('shipping_pickup_ref_type', 'trx')
                ->whereIn('shipping_pickup_ref_id', [
                    $rootId,
                    $agentOrder->getKey(),
                    $companyOrder->getKey(),
                ])
                ->pluck('shipping_pickup_pin')
                ->unique()
                ->values()
                ->all(),
        );

        $this->submitPayment($resellerAccount, $rootId, 'pickup-root');
        $this->verifyPayment($agentAccount, $rootId);

        $this->actingAs($resellerAccount, 'member_api');
        $this->getJson("/api/v1/member/purchases/orders/{$rootId}")
            ->assertOk()
            ->assertJsonPath('data.shipping.code', $rootPickupPin)
            ->assertJsonPath('data.actions.can_show_pickup_code', true);

        $this->assertDatabaseHas('shipping_pickup', [
            'shipping_pickup_ref_type' => 'trx',
            'shipping_pickup_ref_id' => $agentOrder->getKey(),
            'shipping_pickup_seller_name' => $distributor->member_name,
        ]);
        $this->assertDatabaseHas('shipping_pickup_status', [
            'shipping_pickup_status_ref_type' => 'trx',
            'shipping_pickup_status_ref_id' => $agentOrder->getKey(),
            'shipping_pickup_status_value' => 'pending',
        ]);
        $this->assertStock($agent, $product, 0, 0, 0);
        $this->assertStock($distributor, $product, 0, 0, 0);
        $this->assertDatabaseCount('trx', 3);
        $this->assertDatabaseCount('shipping_pickup', 3);

        $this->actingAs($agentAccount, 'member_api');
        $this->getJson("/api/v1/member/sales/orders/{$rootId}")
            ->assertOk()
            ->assertJsonPath('data.shipping.method', 'pickup')
            ->assertJsonPath('data.shipping.location', 'Warehouse Utama DNY')
            ->assertJsonPath('data.actions.can_ship', false);

        $this->submitPayment($agentAccount, $agentOrder->getKey(), 'pickup-agent');
        $this->verifyPayment($distributorAccount, $agentOrder->getKey());

        $this->actingAs($agentAccount, 'member_api');
        $this->getJson("/api/v1/member/purchases/orders/{$agentOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.shipping.code', null)
            ->assertJsonPath('data.actions.can_show_pickup_code', false);

        $administrator = $this->createAdministrator();
        $this->actingAs($administrator, 'admin_api');
        $this->postJson("/api/v1/admin/transactions/orders/{$companyOrder->getKey()}/stock-screening/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'waiting_payment');

        $this->submitPayment($distributorAccount, $companyOrder->getKey(), 'pickup-company');
        $companyPayment = TrxPaymentTransfer::query()
            ->where('payment_transfer_trx_id', $companyOrder->getKey())
            ->sole();
        $this->actingAs($administrator, 'admin_api');
        $this->postJson("/api/v1/admin/transactions/payments/{$companyPayment->getKey()}/approve")
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'approved');
        $this->getJson("/api/v1/admin/inventory/shipments/{$companyOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.shipping_method', 'pickup')
            ->assertJsonPath('data.actions.can_ship', true)
            ->assertJsonPath('data.actions.requires_pickup_pin', true);

        $this->postJson("/api/v1/admin/inventory/shipments/{$companyOrder->getKey()}/ship", [
            'delivery_note_number' => 'SJ-PO-PICKUP-001',
            'pickup_pin' => $rootPickupPin,
            'items' => [[
                'product_id' => $product->getKey(),
                'quantity' => 2,
                'batch_number' => 'BATCH-PO-PICKUP-001',
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
        ])->assertOk()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.shipping.method', 'pickup')
            ->assertJsonPath('data.shipping.verification_status', 'picked_up');

        $this->assertSame(3, Trx::query()
            ->whereIn('trx_id', [$rootId, $agentOrder->getKey(), $companyOrder->getKey()])
            ->where('trx_status', 'received')
            ->count());
        $this->assertDatabaseHas('shipping_pickup', [
            'shipping_pickup_ref_type' => 'trx',
            'shipping_pickup_ref_id' => $rootId,
            'shipping_pickup_delivery_note_number' => 'SJ-PO-PICKUP-001',
        ]);
        $this->assertDatabaseHas('shipping_detail', [
            'shipping_detail_shipping_type' => 'pickup',
            'shipping_detail_product_id' => $product->getKey(),
            'shipping_detail_batch_number' => 'BATCH-PO-PICKUP-001',
            'shipping_detail_qty' => 2,
        ]);

        $this->actingAs($resellerAccount, 'member_api');
        $this->getJson("/api/v1/member/purchases/orders/{$rootId}")
            ->assertOk()
            ->assertJsonPath('data.shipping.method', 'pickup')
            ->assertJsonPath('data.shipping.verification_status', 'picked_up')
            ->assertJsonPath('data.actions.can_receive', true);
        $this->getJson("/api/v1/member/purchases/goods-receipts/{$rootId}")
            ->assertOk()
            ->assertJsonPath('data.seller.type', 'warehouse')
            ->assertJsonPath('data.actions.requires_delivery_note_number', true)
            ->assertJsonPath('data.shipment_items.0.batch_number', 'BATCH-PO-PICKUP-001');
        $this->postJson("/api/v1/member/purchases/goods-receipts/{$rootId}/confirm", [
            'delivery_note_number' => 'SJ-PO-PICKUP-001',
        ])
            ->assertOk()
            ->assertJsonPath('data.status.code', 'completed');
        $this->assertStock($reseller, $product, 2, 0, 0);
        $this->assertSame(3, Trx::query()->where('trx_status', 'completed')->count());
    }

    public function test_manual_courier_preorder_between_members_is_fulfilled_by_terminal_upline(): void
    {
        [$distributor, $distributorAccount] = $this->createMember(1, 1, 0, 'DST', 'Distributor');
        [$agent, $agentAccount] = $this->createMember(2, 2, 1, 'AGT', 'Agent');
        [$reseller, $resellerAccount] = $this->createMember(3, 3, 2, 'RSL', 'Reseller');
        $product = $this->createReferences([$distributor, $agent, $reseller]);
        DB::table('member_stock')
            ->where('member_stock_member_id', $distributor->getKey())
            ->where('member_stock_product_id', $product->getKey())
            ->update(['member_stock_balance' => 5]);
        $addressId = (int) MemberAddress::query()
            ->where('member_address_member_id', $reseller->getKey())
            ->where('member_address_is_default', 1)
            ->value('member_address_id');
        config([
            'services.stc.base_url' => 'https://stc.example.test',
            'services.stc.token' => 'stc-token',
            'services.stc.client_id' => 9,
        ]);
        Http::fake([
            'https://stc.example.test/api/shipping/price-express' => Http::response([
                'status' => true,
                'details' => [],
                'results' => [[
                    'service' => 'sicepat',
                    'service_name' => 'Sicepat Reguler',
                    'service_type' => 'REG',
                    'cost' => 15_000,
                    'etd' => '2-3 hari',
                    'drop' => true,
                    'force_insurance' => false,
                    'insurance' => 0,
                    'logo' => null,
                ]],
            ]),
        ]);

        $this->actingAs($resellerAccount, 'member_api');
        $rootResponse = $this->postJson('/api/v1/member/purchases/orders', [
            'address_id' => $addressId,
            'bank_account_id' => $this->bankAccountId($agent),
            'shipping_method' => 'courier_manual',
            'courier' => [
                'courier_code' => 'sicepat',
                'courier_name' => 'Sicepat Reguler',
                'service_type' => 'REG',
                'cost' => 15_000,
                'etd' => '2-3 hari',
                'drop_off_available' => true,
                'force_insurance' => false,
                'insurance' => 0,
                'logo_url' => null,
            ],
            'items' => [['product_id' => $product->getKey(), 'quantity' => 2]],
        ])->assertOk()
            ->assertJsonPath('data.shipping.method', 'courier_manual')
            ->assertJsonPath('data.shipping.courier', 'sicepat');
        $rootId = (int) $rootResponse->json('data.id');
        $terminalOrder = Trx::query()->where('trx_parent_trx_id', $rootId)->sole();

        $this->assertSame('distributor', $terminalOrder->trx_seller_type);
        $this->assertSame('courier_manual', $terminalOrder->trx_shipping_method);
        $this->assertDatabaseMissing('trx', ['trx_parent_trx_id' => $terminalOrder->getKey()]);
        $this->assertDatabaseCount('shipping_courier_manual', 2);

        $this->submitPayment($resellerAccount, $rootId, 'manual-root');
        $this->verifyPayment($agentAccount, $rootId);
        $this->submitPayment($agentAccount, $terminalOrder->getKey(), 'manual-agent');
        $this->verifyPayment($distributorAccount, $terminalOrder->getKey());

        $this->actingAs($distributorAccount, 'member_api');
        $this->getJson("/api/v1/member/sales/orders/{$terminalOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_ship', true)
            ->assertJsonPath('data.actions.requires_tracking_number', true)
            ->assertJsonPath('data.shipping.method', 'courier_manual');
        $this->postJson("/api/v1/member/sales/orders/{$terminalOrder->getKey()}/ship", [
            'delivery_note_number' => 'SJ-PO-MANUAL-001',
            'tracking_number' => 'SICEPAT-PO-001',
            'items' => [[
                'product_id' => $product->getKey(),
                'quantity' => 2,
                'batch_number' => 'BATCH-PO-MANUAL-001',
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
        ])->assertOk()
            ->assertJsonPath('data.status.code', 'received')
            ->assertJsonPath('data.shipping.method', 'courier_manual')
            ->assertJsonPath('data.shipping.tracking_number', 'SICEPAT-PO-001');

        $this->assertDatabaseHas('trx', ['trx_id' => $rootId, 'trx_status' => 'received']);
        $this->assertDatabaseHas('shipping_courier_manual', [
            'shipping_courier_manual_ref_type' => 'trx',
            'shipping_courier_manual_ref_id' => $rootId,
            'shipping_courier_manual_awb' => 'SICEPAT-PO-001',
            'shipping_courier_manual_delivery_note_number' => 'SJ-PO-MANUAL-001',
        ]);
        $this->assertDatabaseHas('shipping_detail', [
            'shipping_detail_shipping_type' => 'courier_manual',
            'shipping_detail_product_id' => $product->getKey(),
            'shipping_detail_batch_number' => 'BATCH-PO-MANUAL-001',
            'shipping_detail_qty' => 2,
        ]);

        $this->actingAs($resellerAccount, 'member_api');
        $this->getJson("/api/v1/member/purchases/orders/{$rootId}")
            ->assertOk()
            ->assertJsonPath('data.shipping.method', 'courier_manual')
            ->assertJsonPath('data.shipping.tracking_number', 'SICEPAT-PO-001')
            ->assertJsonPath('data.actions.can_receive', true);
        $this->postJson("/api/v1/member/purchases/goods-receipts/{$rootId}/confirm", [])
            ->assertOk()
            ->assertJsonPath('data.status.code', 'completed');
        $this->assertStock($reseller, $product, 2, 0, 0);
        $this->assertStock($distributor, $product, 3, 0, 0);
        $this->assertSame(2, Trx::query()->where('trx_status', 'completed')->count());
    }

    public function test_preorder_cannot_reserve_more_than_terminal_warehouse_stock(): void
    {
        [$distributor] = $this->createMember(1, 1, 0, 'DST', 'Distributor');
        [$agent] = $this->createMember(2, 2, 1, 'AGT', 'Agent');
        [$reseller, $resellerAccount] = $this->createMember(3, 3, 2, 'RSL', 'Reseller');
        $product = $this->createReferences([$distributor, $agent, $reseller]);
        DB::table('warehouse_stock')
            ->where('warehouse_stock_warehouse_id', 1)
            ->where('warehouse_stock_product_id', $product->getKey())
            ->update(['warehouse_stock_balance' => 1]);

        $this->actingAs($resellerAccount, 'member_api');
        $this->postJson('/api/v1/member/purchases/orders', [
            'bank_account_id' => $this->bankAccountId($agent),
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 2]],
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error')
            ->assertJsonPath(
                'message',
                'Pesanan inden tidak dapat dibuat karena stok di seluruh jaringan hingga gudang tidak mencukupi.',
            );

        $this->assertDatabaseCount('trx', 0);
        $this->assertStock($reseller, $product, 0, 0, 0);
        $this->assertStock($agent, $product, 0, 0, 0);
        $this->assertStock($distributor, $product, 0, 0, 0);
        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 1,
            'warehouse_stock_transfer_out' => 0,
        ]);
        $this->assertDatabaseCount('warehouse_stock_log', 0);
    }

    public function test_member_seller_stock_still_cannot_be_negative(): void
    {
        [$distributor] = $this->createMember(1, 1, 0, 'DST', 'Distributor');
        [$agent] = $this->createMember(2, 2, 1, 'AGT', 'Agent');
        [$reseller, $resellerAccount] = $this->createMember(3, 3, 2, 'RSL', 'Reseller');
        $product = $this->createReferences([$distributor, $agent, $reseller]);
        DB::table('member_stock')
            ->where('member_stock_member_id', $agent->getKey())
            ->where('member_stock_product_id', $product->getKey())
            ->update(['member_stock_balance' => 1]);

        $this->actingAs($resellerAccount, 'member_api');
        $this->postJson('/api/v1/member/purchases/orders', [
            'bank_account_id' => $this->bankAccountId($agent),
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 2]],
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error')
            ->assertJsonPath(
                'message',
                'Jumlah Serum PO DNY tidak boleh melebihi stok tersedia. Maksimal 1 pcs.',
            );

        $this->assertDatabaseCount('trx', 0);
        $this->assertStock($agent, $product, 1, 0, 0);
        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 100,
            'warehouse_stock_transfer_out' => 0,
        ]);
    }

    public function test_only_terminal_preorder_seller_can_cancel_entire_paid_chain_and_release_stock(): void
    {
        [$distributor, $distributorAccount] = $this->createMember(1, 1, 0, 'DST', 'Distributor');
        [$agent, $agentAccount] = $this->createMember(2, 2, 1, 'AGT', 'Agent');
        [$reseller, $resellerAccount] = $this->createMember(3, 3, 2, 'RSL', 'Reseller');
        $product = $this->createReferences([$distributor, $agent, $reseller]);
        DB::table('member_stock')
            ->where('member_stock_member_id', $distributor->getKey())
            ->where('member_stock_product_id', $product->getKey())
            ->update(['member_stock_balance' => 5]);

        $this->actingAs($resellerAccount, 'member_api');
        $rootResponse = $this->postJson('/api/v1/member/purchases/orders', [
            'bank_account_id' => $this->bankAccountId($agent),
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 2]],
        ])->assertOk()
            ->assertJsonPath('data.is_preorder', true);
        $rootId = (int) $rootResponse->json('data.id');
        $rootCode = (string) $rootResponse->json('data.code');
        $agentOrder = Trx::query()->where('trx_parent_trx_id', $rootId)->sole();

        $this->assertDatabaseCount('trx', 2);
        $this->assertStock($distributor, $product, 3, 0, 2);
        $this->assertStock($reseller, $product, 0, 2, 0);

        $this->getJson("/api/v1/member/purchases/orders/{$rootId}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_cancel', false);
        $this->postJson("/api/v1/member/purchases/orders/{$rootId}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Pesanan PO hanya dapat dibatalkan oleh penjual PO terakhir.');

        $this->actingAs($agentAccount, 'member_api');
        $this->getJson("/api/v1/member/sales/orders/{$rootId}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_cancel', false);
        $this->postJson("/api/v1/member/sales/orders/{$rootId}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Rantai PO hanya dapat dibatalkan oleh penjual PO terakhir.');

        $this->actingAs($distributorAccount, 'member_api');
        $this->getJson("/api/v1/member/sales/orders/{$agentOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_cancel', true);

        $this->submitPayment($resellerAccount, $rootId, 'root-cancel-child');
        $this->actingAs($distributorAccount, 'member_api');
        $this->getJson("/api/v1/member/sales/orders/{$agentOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_cancel', true);

        $this->verifyPayment($agentAccount, $rootId);
        $this->assertDatabaseCount('member_point_transaction', 0);
        $this->submitPayment($agentAccount, $agentOrder->getKey(), 'agent-cancel-child');
        $this->verifyPayment($distributorAccount, $agentOrder->getKey());

        $this->assertSame(2, Trx::query()->where('trx_status', 'processing')->count());
        $this->assertDatabaseCount('member_point_transaction', 2);

        $this->actingAs($distributorAccount, 'member_api');
        $this->getJson("/api/v1/member/sales/orders/{$agentOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_cancel', true);
        $this->postJson("/api/v1/member/sales/orders/{$agentOrder->getKey()}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status.code', 'cancelled')
            ->assertJsonPath('data.preorder.chain.0.transaction.status', 'cancelled')
            ->assertJsonPath('data.preorder.chain.1.transaction.status', 'cancelled')
            ->assertJsonPath('data.actions.can_cancel', false);

        $this->assertSame(2, Trx::query()->where('trx_status', 'cancelled')->count());
        $this->assertStock($distributor, $product, 5, 0, 0);
        $this->assertStock($reseller, $product, 0, 0, 0);
        $this->assertDatabaseHas('member_stock_log', [
            'member_stock_log_member_id' => $distributor->getKey(),
            'member_stock_log_product_id' => $product->getKey(),
            'member_stock_log_type' => 'in',
            'member_stock_log_quantity' => 2,
            'member_stock_log_note' => "Pembatalan pesanan {$rootCode}",
        ]);
        $this->assertDatabaseCount('member_point_transaction', 0);
        $this->assertDatabaseHas('reward_point_annual', [
            'reward_point_annual_member_id' => $reseller->getKey(),
            'reward_point_annual_total_points' => 0,
        ]);
        $this->assertDatabaseHas('reward_point_annual', [
            'reward_point_annual_member_id' => $agent->getKey(),
            'reward_point_annual_total_points' => 0,
        ]);
        $this->assertDatabaseHas('trx_payment_transfer', [
            'payment_transfer_trx_id' => $rootId,
            'payment_transfer_approval_status' => 'approved',
        ]);
        $this->assertDatabaseHas('trx_payment_transfer', [
            'payment_transfer_trx_id' => $agentOrder->getKey(),
            'payment_transfer_approval_status' => 'approved',
        ]);

        $this->postJson("/api/v1/member/sales/orders/{$agentOrder->getKey()}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Rantai PO pada status saat ini tidak dapat dibatalkan.');
    }

    public function test_admin_deactivation_cancels_member_preorder_chain_once_and_releases_terminal_stock(): void
    {
        [$distributor] = $this->createMember(1, 1, 0, 'DST', 'Distributor');
        [$agent] = $this->createMember(2, 2, 1, 'AGT', 'Agent');
        [$reseller, $resellerAccount] = $this->createMember(3, 3, 2, 'RSL', 'Reseller');
        $product = $this->createReferences([$distributor, $agent, $reseller]);
        DB::table('member_stock')
            ->where('member_stock_member_id', $distributor->getKey())
            ->where('member_stock_product_id', $product->getKey())
            ->update(['member_stock_balance' => 5]);

        $this->actingAs($resellerAccount, 'member_api');
        $rootResponse = $this->postJson('/api/v1/member/purchases/orders', [
            'bank_account_id' => $this->bankAccountId($agent),
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 2]],
        ])->assertOk();
        $rootCode = (string) $rootResponse->json('data.code');

        $this->assertDatabaseCount('trx', 2);
        $this->assertStock($distributor, $product, 3, 0, 2);
        $this->assertStock($reseller, $product, 0, 2, 0);

        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->getJson("/api/v1/admin/partnership/members/{$agent->getKey()}/deactivation-options")
            ->assertOk()
            ->assertJsonPath('data.can_deactivate', true)
            ->assertJsonPath('data.requires_transaction_cancellation', true)
            ->assertJsonPath('data.summary.active_transaction_count', 2)
            ->assertJsonPath('data.summary.cancellable_transaction_count', 2);

        $this->postJson("/api/v1/admin/partnership/members/{$agent->getKey()}/deactivate", [
            'replacement_sponsor_id' => $distributor->getKey(),
            'cancel_active_transactions' => true,
        ])->assertOk()
            ->assertJsonPath('data.cancelled_transactions', 2)
            ->assertJsonPath('data.released_stock_orders', 1)
            ->assertJsonPath('data.moved_downlines', 1);

        $this->assertSame(2, Trx::query()->where('trx_status', 'cancelled')->count());
        $this->assertStock($distributor, $product, 5, 0, 0);
        $this->assertStock($reseller, $product, 0, 0, 0);
        $this->assertDatabaseHas('member', [
            'member_id' => $agent->getKey(),
            'member_status' => 0,
        ]);
        $this->assertDatabaseHas('member', [
            'member_id' => $reseller->getKey(),
            'member_parent_member_id' => $distributor->getKey(),
        ]);
        $this->assertDatabaseHas('member_stock_log', [
            'member_stock_log_member_id' => $distributor->getKey(),
            'member_stock_log_product_id' => $product->getKey(),
            'member_stock_log_type' => 'in',
            'member_stock_log_quantity' => 2,
            'member_stock_log_note' => "Pembatalan pesanan {$rootCode}",
        ]);
    }

    public function test_terminal_preorder_seller_can_cancel_while_previous_payment_awaits_approval(): void
    {
        [$distributor, $distributorAccount] = $this->createMember(1, 1, 0, 'DST', 'Distributor');
        [$agent, $agentAccount] = $this->createMember(2, 2, 1, 'AGT', 'Agent');
        [$reseller, $resellerAccount] = $this->createMember(3, 3, 2, 'RSL', 'Reseller');
        $product = $this->createReferences([$distributor, $agent, $reseller]);
        DB::table('member_stock')
            ->where('member_stock_member_id', $distributor->getKey())
            ->where('member_stock_product_id', $product->getKey())
            ->update(['member_stock_balance' => 5]);

        $this->actingAs($resellerAccount, 'member_api');
        $rootResponse = $this->postJson('/api/v1/member/purchases/orders', [
            'bank_account_id' => $this->bankAccountId($agent),
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 2]],
        ])->assertOk();
        $rootId = (int) $rootResponse->json('data.id');
        $terminalOrder = Trx::query()->where('trx_parent_trx_id', $rootId)->sole();

        $this->actingAs($agentAccount, 'member_api');
        $this->getJson('/api/v1/member/purchases/orders')
            ->assertOk()
            ->assertJsonPath('data.pagination.total_data', 0);

        $this->submitPayment($resellerAccount, $rootId, 'root-awaiting-cancel');
        $this->assertDatabaseCount('member_point_transaction', 0);

        $this->actingAs($distributorAccount, 'member_api');
        $this->postJson("/api/v1/member/sales/orders/{$terminalOrder->getKey()}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status.code', 'cancelled')
            ->assertJsonPath('data.preorder.chain.0.transaction.status', 'cancelled')
            ->assertJsonPath('data.preorder.chain.1.transaction.status', 'cancelled');

        $this->actingAs($agentAccount, 'member_api');
        $this->getJson('/api/v1/member/purchases/orders')
            ->assertOk()
            ->assertJsonPath('data.pagination.total_data', 1)
            ->assertJsonPath('data.results.0.status.code', 'cancelled');

        $this->assertSame(2, Trx::query()->where('trx_status', 'cancelled')->count());
        $this->assertDatabaseCount('member_point_transaction', 0);
        $this->assertStock($distributor, $product, 5, 0, 0);
        $this->assertStock($reseller, $product, 0, 0, 0);
    }

    public function test_preorder_stops_at_first_upline_with_enough_stock(): void
    {
        [$distributor, $distributorAccount] = $this->createMember(1, 1, 0, 'DST', 'Distributor');
        [$agent, $agentAccount] = $this->createMember(2, 2, 1, 'AGT', 'Agent');
        [$reseller, $resellerAccount] = $this->createMember(3, 3, 2, 'RSL', 'Reseller');
        $product = $this->createReferences([$distributor, $agent, $reseller]);
        DB::table('member_stock')
            ->where('member_stock_member_id', $distributor->getKey())
            ->where('member_stock_product_id', $product->getKey())
            ->update(['member_stock_balance' => 5]);

        $this->actingAs($resellerAccount, 'member_api');
        $rootResponse = $this->postJson('/api/v1/member/purchases/orders', [
            'bank_account_id' => $this->bankAccountId($agent),
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 2]],
        ])->assertOk()
            ->assertJsonPath('data.is_preorder', true)
            ->assertJsonPath('data.seller.type', 'agent');
        $rootId = (int) $rootResponse->json('data.id');
        $rootCode = (string) $rootResponse->json('data.code');

        $this->assertStock($distributor, $product, 3, 0, 2);
        $this->assertStock($agent, $product, 0, 0, 0);
        $this->assertStock($reseller, $product, 0, 2, 0);
        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 100,
            'warehouse_stock_transfer_out' => 0,
        ]);
        $this->assertDatabaseHas('member_stock_log', [
            'member_stock_log_member_id' => $distributor->getKey(),
            'member_stock_log_product_id' => $product->getKey(),
            'member_stock_log_type' => 'out',
            'member_stock_log_quantity' => 2,
            'member_stock_log_note' => "Pemesanan {$rootCode}",
        ]);

        $this->submitPayment($resellerAccount, $rootId, 'root-terminal-distributor');
        $this->verifyPayment($agentAccount, $rootId);
        $agentOrder = Trx::query()->where('trx_parent_trx_id', $rootId)->sole();
        $this->assertSame('distributor', $agentOrder->trx_seller_type);

        $this->actingAs($agentAccount, 'member_api');
        $this->getJson("/api/v1/member/sales/orders/{$rootId}")
            ->assertOk()
            ->assertJsonPath('data.order_type.code', 'preorder')
            ->assertJsonPath('data.preorder.current_transaction_id', $rootId)
            ->assertJsonPath('data.preorder.current_member_transaction_id', $rootId)
            ->assertJsonPath('data.preorder.origin.buyer.id', $reseller->getKey())
            ->assertJsonPath('data.preorder.chain.0.buyer.id', $reseller->getKey())
            ->assertJsonPath('data.preorder.chain.0.seller.id', $agent->getKey())
            ->assertJsonPath('data.preorder.chain.0.transaction.payment_status', 'approved')
            ->assertJsonPath('data.preorder.chain.1.buyer.id', $agent->getKey())
            ->assertJsonPath('data.preorder.chain.1.seller.id', $distributor->getKey())
            ->assertJsonPath('data.preorder.chain.1.transaction.payment_status', 'pending')
            ->assertJsonCount(2, 'data.preorder.chain')
            ->assertJsonPath('data.actions.can_ship', false)
            ->assertJsonPath('data.actions.requires_pickup_pin', false);
        $this->getJson("/api/v1/member/purchases/orders/{$agentOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_upload_payment', true)
            ->assertJsonPath('data.actions.can_ship', false);
        $this->getJson('/api/v1/member/sales/orders?filter[action]=ship')
            ->assertOk()
            ->assertJsonPath('data.pagination.total_data', 0);
        $this->getJson('/api/v1/member/sales/orders/summary')
            ->assertOk()
            ->assertJsonPath('data.action_required', 0);

        $rootPickupPin = (string) DB::table('shipping_pickup')
            ->where('shipping_pickup_ref_type', 'trx')
            ->where('shipping_pickup_ref_id', $rootId)
            ->value('shipping_pickup_pin');
        $this->postJson("/api/v1/member/sales/orders/{$rootId}/ship", [
            'delivery_note_number' => 'SJ-PO-INTERMEDIARY-001',
            'pickup_pin' => $rootPickupPin,
            'items' => [[
                'product_id' => $product->getKey(),
                'quantity' => 2,
                'batch_number' => 'BATCH-PO-INTERMEDIARY-001',
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error')
            ->assertJsonPath(
                'message',
                'Pesanan PO ini sedang dipenuhi melalui pesanan pembelian Anda dan tidak perlu dikirim dari stok Anda.'
            );

        $this->submitPayment($agentAccount, $agentOrder->getKey(), 'agent-terminal-distributor');
        $this->verifyPayment($distributorAccount, $agentOrder->getKey());

        $this->assertDatabaseMissing('trx', [
            'trx_parent_trx_id' => $agentOrder->getKey(),
        ]);
        $this->assertDatabaseCount('trx', 2);
        $this->assertStock($distributor, $product, 3, 0, 2);

        $this->actingAs($distributorAccount, 'member_api');
        $this->getJson("/api/v1/member/sales/orders/{$agentOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.order_type.code', 'preorder')
            ->assertJsonPath('data.preorder.current_transaction_id', $agentOrder->getKey())
            ->assertJsonPath('data.preorder.current_member_transaction_id', $agentOrder->getKey())
            ->assertJsonPath('data.preorder.origin.buyer.id', $reseller->getKey())
            ->assertJsonPath('data.preorder.chain.0.buyer.id', $reseller->getKey())
            ->assertJsonPath('data.preorder.chain.0.seller.id', $agent->getKey())
            ->assertJsonPath('data.preorder.chain.1.buyer.id', $agent->getKey())
            ->assertJsonPath('data.preorder.chain.1.seller.id', $distributor->getKey())
            ->assertJsonCount(2, 'data.preorder.chain')
            ->assertJsonPath('data.actions.can_ship', true)
            ->assertJsonPath('data.actions.allows_delivery_note_number', true)
            ->assertJsonPath('data.actions.requires_delivery_note_number', false)
            ->assertJsonPath('data.actions.requires_pickup_pin', true);
        $this->getJson('/api/v1/member/sales/orders?filter[action]=ship')
            ->assertOk()
            ->assertJsonPath('data.pagination.total_data', 1)
            ->assertJsonPath('data.results.0.id', $agentOrder->getKey());
        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 100,
            'warehouse_stock_transfer_out' => 0,
        ]);

        $pickupPin = (string) DB::table('shipping_pickup')
            ->where('shipping_pickup_ref_type', 'trx')
            ->where('shipping_pickup_ref_id', $agentOrder->getKey())
            ->value('shipping_pickup_pin');
        $shippingPayload = [
            'items' => [[
                'product_id' => $product->getKey(),
                'quantity' => 2,
                'batch_number' => 'BATCH-PO-PICKUP-DST-001',
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
        ];

        $this->actingAs($distributorAccount, 'member_api');
        $this->postJson("/api/v1/member/sales/orders/{$agentOrder->getKey()}/ship", $shippingPayload)
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonPath('errors.pickup_pin.0', 'Kode pengambilan wajib diisi untuk metode ambil di tempat.');

        $this->postJson("/api/v1/member/sales/orders/{$agentOrder->getKey()}/ship", [
            ...$shippingPayload,
            'pickup_pin' => $pickupPin,
        ])->assertOk()
            ->assertJsonPath('data.status.code', 'received')
            ->assertJsonPath('data.status.label', 'Siap Diterima')
            ->assertJsonPath('data.shipping.verification_status', 'picked_up');

        $this->assertDatabaseHas('trx', ['trx_id' => $rootId, 'trx_status' => 'received']);
        $this->assertDatabaseHas('trx', ['trx_id' => $agentOrder->getKey(), 'trx_status' => 'received']);
        $this->assertDatabaseHas('shipping_pickup_status', [
            'shipping_pickup_status_ref_type' => 'trx',
            'shipping_pickup_status_ref_id' => $agentOrder->getKey(),
            'shipping_pickup_status_value' => 'picked_up',
        ]);
        $this->assertDatabaseHas('shipping_pickup', [
            'shipping_pickup_ref_type' => 'trx',
            'shipping_pickup_ref_id' => $agentOrder->getKey(),
            'shipping_pickup_delivery_note_number' => null,
        ]);
        $this->assertStock($distributor, $product, 3, 0, 0);
    }

    public function test_preorder_uses_terminal_seller_origin_when_direct_seller_stock_is_reserved(): void
    {
        [$distributor] = $this->createMember(1, 1, 0, 'DST', 'Distributor');
        [$agent, $agentAccount] = $this->createMember(2, 2, 1, 'AGT', 'Agent');
        [$reseller, $resellerAccount] = $this->createMember(3, 3, 2, 'RSL', 'Reseller');
        $product = $this->createReferences([$distributor, $agent, $reseller]);
        DB::table('member_stock')
            ->where('member_stock_member_id', $agent->getKey())
            ->where('member_stock_product_id', $product->getKey())
            ->update([
                // Balance is already net of existing checkout reservations.
                'member_stock_balance' => 0,
                'member_stock_transfer_out' => 2,
            ]);
        DB::table('member_stock')
            ->where('member_stock_member_id', $distributor->getKey())
            ->where('member_stock_product_id', $product->getKey())
            ->update(['member_stock_balance' => 5]);

        $this->actingAs($resellerAccount, 'member_api');
        $rootResponse = $this->postJson('/api/v1/member/purchases/orders', [
            'bank_account_id' => $this->bankAccountId($agent),
            'shipping_method' => 'pickup',
            'items' => [['product_id' => $product->getKey(), 'quantity' => 2]],
        ])->assertOk()
            ->assertJsonPath('data.is_preorder', true)
            ->assertJsonPath('data.seller.type', 'agent')
            ->assertJsonPath('data.seller.origin.name', $distributor->member_name);

        $rootId = (int) $rootResponse->json('data.id');

        $this->assertStock($agent, $product, 0, 0, 2);
        $this->assertStock($distributor, $product, 3, 0, 2);

        $this->submitPayment($resellerAccount, $rootId, 'reserved-origin-root');
        $this->verifyPayment($agentAccount, $rootId);

        $agentOrder = Trx::query()
            ->where('trx_parent_trx_id', $rootId)
            ->sole();

        $this->actingAs($agentAccount, 'member_api');
        $this->getJson("/api/v1/member/purchases/orders/{$agentOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.seller.type', 'distributor')
            ->assertJsonPath('data.seller.origin.name', $distributor->member_name)
            ->assertJsonPath('data.actions.can_upload_payment', true)
            ->assertJsonPath('data.actions.can_ship', false);
    }

    public function test_preorder_chain_creates_connected_database_rows_and_records_each_purchase_reward_once(): void
    {
        [$distributor, $distributorAccount] = $this->createMember(1, 1, 0, 'DST', 'Distributor');
        [$agent, $agentAccount] = $this->createMember(2, 2, 1, 'AGT', 'Agent');
        [$reseller, $resellerAccount] = $this->createMember(3, 3, 2, 'RSL', 'Reseller');
        $product = $this->createReferences([$distributor, $agent, $reseller]);
        $address = MemberAddress::query()
            ->where('member_address_member_id', $reseller->getKey())
            ->where('member_address_is_default', 1)
            ->firstOrFail();
        config([
            'services.stc.base_url' => 'https://stc.example.test',
            'services.stc.token' => 'stc-token',
            'services.stc.client_id' => 9,
        ]);
        Http::fake([
            'https://stc.example.test/api/shipping/price-express' => Http::response([
                'status' => true,
                'details' => [],
                'results' => [[
                    'service' => 'jne',
                    'service_name' => 'JNE Express Reguler',
                    'service_type' => 'REG23',
                    'cost' => 20_000,
                    'etd' => '2-3',
                    'drop' => true,
                    'force_insurance' => false,
                    'insurance' => 0,
                    'logo' => 'https://stc.example.test/jne.png',
                ]],
            ]),
            'https://stc.example.test/api/shipping/pickup-express' => Http::response([
                'status' => true,
                'pickup_number' => 'PICKUP-PO-CHAIN-001',
                'details' => [[
                    'order_id' => 'STC-PO-CHAIN-001',
                    'awb' => 'JNE-PO-CHAIN-001',
                ]],
            ]),
            'https://stc.example.test/api/shipping/tracking-express' => Http::response([
                'message' => 'Paket sedang dalam perjalanan.',
                'error' => '',
                'data' => ['results' => [
                    'method' => 'shTracking',
                    'details' => [
                        'awb' => 'JNE-PO-CHAIN-001',
                        'order_id' => 'STC-PO-CHAIN-001',
                        'delivered' => false,
                    ],
                    'histories' => [[
                        'created_at' => '2026-09-02 10:00:00',
                        'status' => 'Paket sedang dalam perjalanan.',
                        'status_code' => 100,
                        'driver' => '',
                        'receiver' => '',
                    ]],
                ]],
            ]),
        ]);

        $this->actingAs($resellerAccount, 'member_api');
        $rootResponse = $this->postJson('/api/v1/member/purchases/orders', [
            'address_id' => $address->getKey(),
            'bank_account_id' => $this->bankAccountId($agent),
            'shipping_method' => 'courier_express',
            'courier' => [
                'courier_code' => 'jne',
                'courier_name' => 'JNE Express Reguler',
                'service_type' => 'REG23',
                'cost' => 20_000,
                'etd' => '2-3',
                'drop_off_available' => true,
                'force_insurance' => false,
                'insurance' => 0,
                'logo_url' => 'https://stc.example.test/jne.png',
            ],
            'items' => [['product_id' => $product->getKey(), 'quantity' => 2]],
        ])->assertOk()
            ->assertJsonPath('data.is_preorder', true)
            ->assertJsonPath('data.seller.type', 'agent');
        $rootId = (int) $rootResponse->json('data.id');
        $rootCode = (string) $rootResponse->json('data.code');
        $rootAmount = (int) $rootResponse->json('data.summary.grand_total');
        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 98,
            'warehouse_stock_transfer_out' => 2,
        ]);
        $this->assertDatabaseHas('warehouse_stock_log', [
            'warehouse_stock_log_warehouse_id' => 1,
            'warehouse_stock_log_product_id' => $product->getKey(),
            'warehouse_stock_log_type' => 'out',
            'warehouse_stock_log_quantity' => 2,
            'warehouse_stock_log_note' => "Pemesanan {$rootCode}",
        ]);
        $this->assertSame(300_000, $rootAmount);
        $this->assertDatabaseHas('shipping_courier_express', [
            'shipping_courier_express_ref_type' => 'trx',
            'shipping_courier_express_ref_id' => $rootId,
            'shipping_courier_express_expedition_name' => 'jne',
            'shipping_courier_express_expedition_service' => 'JNE Express Reguler',
            'shipping_courier_express_type' => 'REG23',
        ]);
        $this->assertStock($reseller, $product, 0, 2, 0);
        $this->assertStock($agent, $product, 0, 0, 0);

        $agentOrder = Trx::query()->where('trx_parent_trx_id', $rootId)->sole();
        $companyOrder = Trx::query()->where('trx_parent_trx_id', $agentOrder->getKey())->sole();
        $this->assertSame('waiting_stock_screening', $companyOrder->trx_status);

        // Tahap PO berikutnya sudah tercatat, tetapi belum muncul sebagai
        // pembelian upline sebelum pembayaran tahap di bawahnya disetujui.
        $this->actingAs($resellerAccount, 'member_api');
        $this->getJson('/api/v1/member/purchases/orders')
            ->assertOk()
            ->assertJsonPath('data.pagination.total_data', 1)
            ->assertJsonPath('data.results.0.id', $rootId);
        $this->actingAs($agentAccount, 'member_api');
        $this->getJson('/api/v1/member/purchases/orders')
            ->assertOk()
            ->assertJsonPath('data.pagination.total_data', 0)
            ->assertJsonCount(0, 'data.results');
        $this->actingAs($distributorAccount, 'member_api');
        $this->getJson('/api/v1/member/purchases/orders')
            ->assertOk()
            ->assertJsonPath('data.pagination.total_data', 0)
            ->assertJsonCount(0, 'data.results');
        $this->getJson('/api/v1/member/purchases/orders/summary')
            ->assertOk()
            ->assertJsonPath('data.purchases.total', 0);

        $administrator = $this->createAdministrator();
        $this->actingAs($administrator, 'admin_api');
        $this->getJson('/api/v1/admin/transactions/orders?filter[status]=waiting_stock_screening&filter[can_approve_stock_screening]=1')
            ->assertOk()
            ->assertJsonCount(0, 'data.results');
        $this->getJson("/api/v1/admin/transactions/orders/{$companyOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_approve_stock_screening', false)
            ->assertJsonPath('data.actions.can_reject_stock_screening', false);
        $this->postJson("/api/v1/admin/transactions/orders/{$companyOrder->getKey()}/stock-screening/approve")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Pembayaran PO tahap sebelumnya harus disetujui terlebih dahulu.');
        $this->postJson("/api/v1/admin/transactions/orders/{$companyOrder->getKey()}/stock-screening/reject")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Pembayaran PO tahap sebelumnya harus disetujui terlebih dahulu.');

        $this->actingAs($agentAccount, 'member_api');
        $this->getJson("/api/v1/member/sales/orders/{$rootId}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_verify_payment', false)
            ->assertJsonPath('data.actions.can_cancel', false);
        $this->postJson("/api/v1/member/sales/orders/{$rootId}/payment/approve")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Bukti pembayaran belum dikirim oleh pembeli.');

        $this->actingAs($distributorAccount, 'member_api');
        $this->getJson("/api/v1/member/sales/orders/{$agentOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_verify_payment', false)
            ->assertJsonPath('data.actions.can_cancel', false);
        $this->postJson("/api/v1/member/sales/orders/{$agentOrder->getKey()}/payment/approve")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Bukti pembayaran belum dikirim oleh pembeli.');

        $this->actingAs($agentAccount, 'member_api');
        $this->getJson("/api/v1/member/purchases/orders/{$agentOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_upload_payment', false)
            ->assertJsonPath('data.actions.waiting_for_previous_approval', true);
        $this->postJson("/api/v1/member/purchases/orders/{$agentOrder->getKey()}/payment", [
            'receipt_url' => 'https://cdn.example.test/payment/agent-too-early.webp',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Pembayaran PO tahap sebelumnya harus disetujui terlebih dahulu.');

        $this->actingAs($resellerAccount, 'member_api');
        $this->getJson("/api/v1/member/purchases/orders/{$rootId}")
            ->assertOk()
            ->assertJsonPath('data.preorder.current_transaction_id', $rootId)
            ->assertJsonPath('data.preorder.origin.buyer.id', $reseller->getKey())
            ->assertJsonPath('data.preorder.chain.0.transaction.id', $rootId)
            ->assertJsonPath('data.preorder.chain.0.transaction.is_projected', false)
            ->assertJsonPath('data.preorder.chain.0.buyer.id', $reseller->getKey())
            ->assertJsonPath('data.preorder.chain.0.seller.id', $agent->getKey())
            ->assertJsonPath('data.preorder.chain.1.transaction.id', $agentOrder->getKey())
            ->assertJsonPath('data.preorder.chain.1.transaction.is_projected', false)
            ->assertJsonPath('data.preorder.chain.1.transaction.payment_status', 'pending')
            ->assertJsonPath('data.preorder.chain.1.buyer.id', $agent->getKey())
            ->assertJsonPath('data.preorder.chain.1.seller.id', $distributor->getKey())
            ->assertJsonPath('data.preorder.chain.2.transaction.id', $companyOrder->getKey())
            ->assertJsonPath('data.preorder.chain.2.transaction.is_projected', false)
            ->assertJsonPath('data.preorder.chain.2.transaction.payment_status', 'pending')
            ->assertJsonPath('data.preorder.chain.2.seller.type', 'warehouse')
            ->assertJsonCount(3, 'data.preorder.chain');

        $this->submitPayment($resellerAccount, $rootId, 'root');
        $this->verifyPayment($agentAccount, $rootId);
        $this->assertDatabaseCount('member_point_transaction', 0);
        $this->actingAs($agentAccount, 'member_api');
        $this->getJson('/api/v1/member/purchases/orders')
            ->assertOk()
            ->assertJsonPath('data.pagination.total_data', 1)
            ->assertJsonPath('data.results.0.id', $agentOrder->getKey());
        $this->getJson("/api/v1/member/purchases/orders/{$agentOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_upload_payment', true)
            ->assertJsonPath('data.actions.waiting_for_previous_approval', false);
        $this->assertSame('distributor', $agentOrder->trx_seller_type);
        $this->assertSame($agent->getKey(), $agentOrder->trx_buyer_id);
        $this->assertSame(
            explode('/', $rootCode)[3],
            explode('/', $agentOrder->trx_code)[3],
        );
        $this->assertSame(260_000, (int) $agentOrder->trx_bill_amount);
        $this->assertDatabaseMissing('trx_spread_payment', [
            'trx_spread_payment_trx_id' => $agentOrder->getKey(),
        ]);
        $this->assertStock($agent, $product, 0, 0, 0);
        $this->assertStock($distributor, $product, 0, 0, 0);

        $this->submitPayment($agentAccount, $agentOrder->getKey(), 'agent');
        $this->verifyPayment($distributorAccount, $agentOrder->getKey());
        $this->assertDatabaseCount('member_point_transaction', 0);
        $companyOrder->refresh();
        $this->actingAs($distributorAccount, 'member_api');
        $this->getJson('/api/v1/member/purchases/orders')
            ->assertOk()
            ->assertJsonPath('data.pagination.total_data', 1)
            ->assertJsonPath('data.results.0.id', $companyOrder->getKey());
        $this->assertSame('warehouse', $companyOrder->trx_seller_type);
        $this->assertSame('courier_express', $companyOrder->trx_shipping_method);
        $this->assertSame($distributor->getKey(), $companyOrder->trx_buyer_id);
        $this->assertSame('waiting_stock_screening', $companyOrder->trx_status);
        $this->assertSame(
            explode('/', $rootCode)[3],
            explode('/', $companyOrder->trx_code)[3],
        );
        $this->assertSame(220_000, (int) $companyOrder->trx_bill_amount);
        $this->assertDatabaseHas('shipping_courier_express', [
            'shipping_courier_express_ref_type' => 'trx',
            'shipping_courier_express_ref_id' => $companyOrder->getKey(),
            'shipping_courier_express_expedition_name' => 'jne',
            'shipping_courier_express_expedition_service' => 'JNE Express Reguler',
            'shipping_courier_express_type' => 'REG23',
        ]);
        $this->assertStock($distributor, $product, 0, 0, 0);
        $this->actingAs($administrator, 'admin_api');
        $this->getJson('/api/v1/admin/transactions/orders?filter[status]=waiting_stock_screening&filter[can_approve_stock_screening]=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $companyOrder->getKey())
            ->assertJsonPath('data.results.0.actions.can_approve_stock_screening', true)
            ->assertJsonPath('data.results.0.actions.can_reject_stock_screening', true);
        $this->getJson("/api/v1/admin/transactions/orders/{$companyOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_approve_stock_screening', true)
            ->assertJsonPath('data.actions.can_reject_stock_screening', true);
        $this->actingAs($distributorAccount, 'member_api');
        $this->getJson("/api/v1/member/purchases/orders/{$companyOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.status.code', 'waiting_stock_screening')
            ->assertJsonPath('data.actions.can_upload_payment', false)
            ->assertJsonPath('data.stock_screening.status', 'pending')
            ->assertJsonPath('data.order_type.code', 'preorder')
            ->assertJsonPath('data.preorder.current_transaction_id', $companyOrder->getKey())
            ->assertJsonPath('data.preorder.current_member_transaction_id', $companyOrder->getKey())
            ->assertJsonPath('data.preorder.origin.buyer.id', $reseller->getKey())
            ->assertJsonPath('data.preorder.chain.0.seller.id', $agent->getKey())
            ->assertJsonPath('data.preorder.chain.1.seller.id', $distributor->getKey())
            ->assertJsonPath('data.preorder.chain.2.seller.type', 'warehouse')
            ->assertJsonPath('data.preorder.chain.0.transaction.payment_status', 'approved')
            ->assertJsonPath('data.preorder.chain.1.transaction.payment_status', 'approved')
            ->assertJsonPath('data.preorder.chain.2.transaction.payment_status', 'pending')
            ->assertJsonCount(3, 'data.preorder.chain');
        $this->getJson("/api/v1/member/sales/orders/{$agentOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.preorder.current_transaction_id', $agentOrder->getKey())
            ->assertJsonPath('data.preorder.origin.buyer.id', $reseller->getKey())
            ->assertJsonPath('data.preorder.chain.1.seller.id', $distributor->getKey())
            ->assertJsonPath('data.preorder.chain.2.seller.type', 'warehouse')
            ->assertJsonCount(3, 'data.preorder.chain');
        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 98,
            'warehouse_stock_transfer_out' => 2,
        ]);
        $this->assertDatabaseHas('warehouse_stock_log', [
            'warehouse_stock_log_warehouse_id' => 1,
            'warehouse_stock_log_product_id' => $product->getKey(),
            'warehouse_stock_log_type' => 'out',
            'warehouse_stock_log_quantity' => 2,
            'warehouse_stock_log_note' => "Pemesanan {$rootCode}",
        ]);
        $outMutationCount = DB::table('warehouse_stock_log')
            ->where('warehouse_stock_log_note', "Pemesanan {$rootCode}")
            ->count();

        $this->submitPayment($distributorAccount, $companyOrder->getKey(), 'company');
        $this->actingAs($distributorAccount, 'member_api');
        $this->getJson("/api/v1/member/purchases/orders/{$companyOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath(
                'data.payment.receipt_url',
                'https://cdn.example.test/payment/company.webp',
            )
            ->assertJsonPath('data.preorder.chain.2.transaction.payment_status', 'submitted')
            ->assertJsonCount(3, 'data.preorder.chain');
        $companyPayment = TrxPaymentTransfer::query()
            ->where('payment_transfer_trx_id', $companyOrder->getKey())
            ->sole();
        $this->actingAs($administrator, 'admin_api');
        $this->postJson("/api/v1/admin/transactions/payments/{$companyPayment->getKey()}/approve", [
            'note' => 'Pembayaran perusahaan sesuai.',
        ])->assertOk()
            ->assertJsonPath('data.payment.status', 'approved');
        $this->assertDatabaseCount('member_point_transaction', 3);
        $companyOrder->shippingExpress()->update([
            'shipping_courier_express_type' => 'JNE Express Reguler',
        ]);
        $this->postJson("/api/v1/admin/inventory/shipments/{$companyOrder->getKey()}/ship", [
            'delivery_note_number' => 'SJ-PO-CHAIN-001',
            'pickup_method' => 'DROP-OFF',
            'pickup_schedule' => now()->addDay()->setTime(14, 0)->toDateTimeString(),
            'items' => [[
                'product_id' => $product->getKey(),
                'quantity' => 2,
                'batch_number' => 'BATCH-PO-CHAIN-001',
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
        ])->assertOk()
            ->assertJsonPath('data.status', 'shipped');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://stc.example.test/api/shipping/pickup-express'
            && $request['packages'][0]['service'] === 'jne'
            && $request['packages'][0]['service_type'] === 'REG23'
        );
        $this->getJson("/api/v1/admin/inventory/shipments/{$rootId}")
            ->assertOk()
            ->assertJsonPath('data.buyer.destination.code', $reseller->member_code)
            ->assertJsonPath('data.tracking.transaction_id', $companyOrder->getKey())
            ->assertJsonPath('data.tracking.order_id', 'STC-PO-CHAIN-001')
            ->assertJsonPath('data.tracking.tracking_number', 'JNE-PO-CHAIN-001')
            ->assertJsonPath('data.actions.can_track', true)
            ->assertJsonPath('data.actions.can_simulate_finished', true);
        $this->postJson("/api/v1/admin/inventory/shipments/{$rootId}/tracking")
            ->assertOk()
            ->assertJsonPath('data.details.order_id', 'STC-PO-CHAIN-001');
        DB::table('shipping_courier_express')
            ->where('shipping_courier_express_ref_type', 'trx')
            ->where('shipping_courier_express_ref_id', $rootId)
            ->update([
                'shipping_courier_express_order_id' => 'STC-PO-CHAIN-001',
                'shipping_courier_express_awb' => 'LEGACY-AWB-PO-CHAIN-001',
            ]);
        DB::table('shipping_courier_express')
            ->where('shipping_courier_express_ref_type', 'trx')
            ->where('shipping_courier_express_ref_id', $companyOrder->getKey())
            ->update(['shipping_courier_express_awb' => '']);
        $this->getJson('/api/v1/admin/inventory/shipments?filter[buyer.type]=distributor')
            ->assertOk()
            ->assertJsonPath('data.results.0.id', $companyOrder->getKey())
            ->assertJsonPath('data.results.0.tracking.tracking_number', 'LEGACY-AWB-PO-CHAIN-001')
            ->assertJsonPath('data.results.0.actions.can_track', true);
        $this->getJson("/api/v1/admin/inventory/shipments/{$companyOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.buyer.code', $distributor->member_code)
            ->assertJsonPath('data.buyer.destination.code', $reseller->member_code)
            ->assertJsonPath('data.buyer.destination.type', 'reseller')
            ->assertJsonPath('data.tracking.transaction_id', $rootId)
            ->assertJsonPath('data.tracking.tracking_number', 'LEGACY-AWB-PO-CHAIN-001')
            ->assertJsonPath('data.actions.can_track', true);
        $this->postJson("/api/v1/admin/inventory/shipments/{$companyOrder->getKey()}/tracking")
            ->assertOk()
            ->assertJsonPath('data.details.order_id', 'STC-PO-CHAIN-001');
        DB::table('shipping_courier_express')
            ->where('shipping_courier_express_ref_type', 'trx')
            ->where('shipping_courier_express_ref_id', $rootId)
            ->update([
                'shipping_courier_express_order_id' => '',
                'shipping_courier_express_awb' => null,
            ]);
        DB::table('shipping_courier_express')
            ->where('shipping_courier_express_ref_type', 'trx')
            ->where('shipping_courier_express_ref_id', $companyOrder->getKey())
            ->update(['shipping_courier_express_awb' => 'JNE-PO-CHAIN-001']);
        $this->assertDatabaseHas('shipping_courier_express', [
            'shipping_courier_express_ref_id' => $companyOrder->getKey(),
            'shipping_courier_express_type' => 'REG23',
        ]);

        $this->actingAs($distributorAccount, 'member_api');
        $this->getJson("/api/v1/member/purchases/orders/{$companyOrder->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.tracking.is_available', true)
            ->assertJsonPath('data.tracking.provider', 'stc')
            ->assertJsonPath('data.tracking.method', 'courier_express')
            ->assertJsonPath('data.tracking.order_id', 'STC-PO-CHAIN-001')
            ->assertJsonPath('data.tracking.tracking_number', 'JNE-PO-CHAIN-001')
            ->assertJsonPath('data.tracking.status', 'processed_packages')
            ->assertJsonPath(
                'data.tracking.histories.0.status',
                'Paket sedang dalam perjalanan.',
            );

        $this->actingAs($agentAccount, 'member_api');
        $this->getJson("/api/v1/member/sales/orders/{$rootId}")
            ->assertOk()
            ->assertJsonPath('data.tracking.is_available', true)
            ->assertJsonPath('data.tracking.provider', 'stc')
            ->assertJsonPath('data.tracking.method', 'courier_express')
            ->assertJsonPath('data.tracking.order_id', 'STC-PO-CHAIN-001')
            ->assertJsonPath('data.tracking.tracking_number', 'JNE-PO-CHAIN-001')
            ->assertJsonPath(
                'data.tracking.histories.0.status_code',
                100,
            );

        $this->actingAs($resellerAccount, 'member_api');
        $this->getJson('/api/v1/member/purchases/goods-receipts')
            ->assertOk()
            ->assertJsonCount(0, 'data.results');
        $this->getJson('/api/v1/member/purchases/orders/summary')
            ->assertOk()
            ->assertJsonPath('data.goods_receipts.ready_to_receive', 0);
        $this->getJson("/api/v1/member/purchases/goods-receipts/{$rootId}")
            ->assertOk()
            ->assertJsonPath('data.status.code', 'waiting_delivery')
            ->assertJsonPath('data.actions.can_receive', false);
        $this->postJson("/api/v1/member/purchases/goods-receipts/{$rootId}/confirm", [
            'delivery_note_number' => 'SJ-PO-CHAIN-001',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Pengiriman belum selesai dan belum dapat diterima.');

        $this->postJson('/api/v1/callbacks/stc/shipping', [
            'method' => 'finished_packages',
            'data' => [[
                'order_id' => 'STC-PO-CHAIN-001',
                'awb' => 'JNE-PO-CHAIN-001',
                'finished_at' => now()->toDateTimeString(),
            ]],
        ])->assertOk();
        $this->assertSame(
            3,
            Trx::query()
                ->whereIn('trx_id', [$rootId, $agentOrder->getKey(), $companyOrder->getKey()])
                ->where('trx_status', 'received')
                ->count(),
        );
        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 98,
            'warehouse_stock_transfer_out' => 0,
        ]);
        $this->assertDatabaseHas('trx', ['trx_id' => $rootId, 'trx_status' => 'received']);
        $this->assertDatabaseHas('shipping_courier_express', [
            'shipping_courier_express_ref_id' => $rootId,
            'shipping_courier_express_order_id' => '',
        ]);
        $this->assertDatabaseHas('warehouse_stock_log', [
            'warehouse_stock_log_warehouse_id' => 1,
            'warehouse_stock_log_product_id' => $product->getKey(),
            'warehouse_stock_log_type' => 'out',
            'warehouse_stock_log_quantity' => 2,
            'warehouse_stock_log_note' => "Pemesanan {$rootCode}",
        ]);
        $this->assertSame($outMutationCount, DB::table('warehouse_stock_log')
            ->where('warehouse_stock_log_note', "Pemesanan {$rootCode}")
            ->count());

        $this->actingAs($distributorAccount, 'member_api');
        $this->getJson('/api/v1/member/purchases/goods-receipts')
            ->assertOk()
            ->assertJsonCount(0, 'data.results');
        $this->getJson('/api/v1/member/purchases/orders/summary')
            ->assertOk()
            ->assertJsonPath('data.goods_receipts.ready_to_receive', 0);

        $this->actingAs($resellerAccount, 'member_api');
        $this->getJson('/api/v1/member/purchases/goods-receipts')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.purchase_order_id', $rootId)
            ->assertJsonPath('data.results.0.seller.type', 'warehouse')
            ->assertJsonPath('data.results.0.actions.can_receive', true)
            ->assertJsonPath('data.results.0.actions.requires_delivery_note_number', true);
        $this->getJson('/api/v1/member/purchases/orders/summary')
            ->assertOk()
            ->assertJsonPath('data.goods_receipts.ready_to_receive', 1);
        $this->postJson("/api/v1/member/purchases/goods-receipts/{$rootId}/confirm", [
            'delivery_note_number' => 'SJ-PO-CHAIN-001',
        ])->assertOk()
            ->assertJsonPath('data.status.code', 'completed');
        $this->assertDatabaseHas('goods_receive', [
            'goods_receive_trx_id' => $rootId,
            'goods_receive_buyer_id' => $reseller->getKey(),
            'goods_receive_delivery_note_number' => 'SJ-PO-CHAIN-001',
        ]);
        $this->assertDatabaseHas('goods_receive_detail', [
            'goods_receive_detail_product_id' => $product->getKey(),
            'goods_receive_detail_batch_number' => 'BATCH-PO-CHAIN-001',
            'goods_receive_detail_qty' => 2,
        ]);
        $this->assertStock($reseller, $product, 2, 0, 0);
        $this->assertStock($agent, $product, 0, 0, 0);

        $this->actingAs($distributorAccount, 'member_api');
        $this->getJson('/api/v1/member/purchases/goods-receipts')
            ->assertOk()
            ->assertJsonCount(0, 'data.results');
        $this->getJson('/api/v1/member/purchases/orders/summary')
            ->assertOk()
            ->assertJsonPath('data.goods_receipts.ready_to_receive', 0);
        $this->assertStock($distributor, $product, 0, 0, 0);
        $this->assertSame(3, Trx::query()->where('trx_status', 'completed')->count());

        $this->assertDatabaseCount('trx', 3);
        $this->assertDatabaseCount('trx_detail', 3);
        $this->assertSame(3, DB::table('trx_detail')
            ->where('trx_detail_product_bpom_number', 'NA18250100004')
            ->count());
        $this->assertDatabaseCount('trx_payment_transfer', 3);
        $this->assertDatabaseCount('shipping_courier_manual', 0);
        $this->assertDatabaseCount('shipping_courier_express', 3);
        foreach ([
            [$reseller, $rootId],
            [$agent, $agentOrder->getKey()],
            [$distributor, $companyOrder->getKey()],
        ] as [$rewardMember, $rewardTransactionId]) {
            $this->assertDatabaseHas('member_point_transaction', [
                'member_point_transaction_member_id' => $rewardMember->getKey(),
                'member_point_transaction_trx_id' => $rewardTransactionId,
                'member_point_transaction_quantity' => 2,
            ]);
            $this->assertDatabaseHas('reward_point_annual', [
                'reward_point_annual_member_id' => $rewardMember->getKey(),
                'reward_point_annual_total_points' => 2,
            ]);
        }
        $this->assertDatabaseCount('member_point_transaction', 3);
        $this->assertDatabaseCount('reward_point_annual_log', 3);
        $this->assertDatabaseCount('member_achievement', 0);
    }

    /** @return array{Member, MemberAccount} */
    private function createMember(int $id, int $levelId, int $parentId, string $levelCode, string $name): array
    {
        DB::table('member_level')->updateOrInsert(
            ['member_level_id' => $levelId],
            [
                'member_level_code' => $levelCode,
                'member_level_name' => $name,
                'member_level_description' => $name,
                'member_level_min_order' => 0,
                'member_level_point_value' => 1,
                'member_level_sort_order' => $levelId,
                'member_level_is_active' => 1,
            ],
        );
        $member = Member::query()->create([
            'member_id' => $id,
            'member_code' => 'DNY'.str_pad((string) $id, 6, '0', STR_PAD_LEFT),
            'member_member_level_id' => $levelId,
            'member_parent_member_id' => $parentId,
            'member_name' => "{$name} DNY",
            'member_email' => mb_strtolower($name).'@example.test',
            'member_mobilephone' => '08120000000'.$id,
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);
        $group = MemberGroup::query()->firstOrCreate(
            ['member_group_name' => 'Mitra'],
            ['member_group_description' => 'Mitra', 'member_group_is_active' => 1],
        );
        $account = MemberAccount::query()->create([
            'member_account_member_id' => $id,
            'member_account_member_group_id' => $group->getKey(),
            'member_account_username' => mb_strtolower($name).'.dny',
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '',
        ]);

        return [$member, $account];
    }

    /** @param list<Member> $members */
    private function createReferences(array $members): Product
    {
        DB::table('ref_province')->insert([
            ['province_id' => 31, 'province_name' => 'DKI Jakarta', 'province_is_active' => 1],
            ['province_id' => 35, 'province_name' => 'Jawa Timur', 'province_is_active' => 1],
        ]);
        DB::table('ref_city')->insert([
            ['city_id' => 3171, 'city_province_id' => 31, 'city_name' => 'Jakarta Selatan', 'city_type' => 'Kota', 'city_is_active' => 1],
            ['city_id' => 3578, 'city_province_id' => 35, 'city_name' => 'Surabaya', 'city_type' => 'Kota', 'city_is_active' => 1],
        ]);
        DB::table('ref_district')->insert([
            ['district_id' => 317101, 'district_city_id' => 3171, 'district_name' => 'Kebayoran Baru'],
            ['district_id' => 357801, 'district_city_id' => 3578, 'district_name' => 'Tegalsari'],
        ]);
        DB::table('ref_subdistrict')->insert([
            ['subdistrict_id' => 31710101, 'subdistrict_district_id' => 317101, 'subdistrict_name' => 'Senayan', 'subdistrict_zip_code' => 12190],
            ['subdistrict_id' => 35780101, 'subdistrict_district_id' => 357801, 'subdistrict_name' => 'Kedungdoro', 'subdistrict_zip_code' => 60261],
        ]);
        DB::table('ref_country')->insert(['country_id' => 1, 'country_name' => 'Indonesia']);
        foreach ($members as $member) {
            MemberAddress::query()->create([
                'member_address_member_id' => $member->getKey(),
                'member_address_label' => 'Alamat Utama',
                'member_address_recipient' => $member->member_name,
                'member_address_phone' => $member->member_mobilephone,
                'member_address_full' => "Jalan {$member->member_name}",
                'member_address_province_id' => 35,
                'member_address_city_id' => 3578,
                'member_address_district_id' => 357801,
                'member_address_subdistrict_id' => 35780101,
                'member_address_country_id' => 1,
                'member_address_is_default' => 1,
            ]);
        }
        DB::table('ref_bank')->insert([
            'bank_id' => 1,
            'bank_code' => 'DNY',
            'bank_name' => 'Bank DNY',
            'bank_is_active' => 1,
        ]);
        DB::table('bank_company')->insert([
            [
                'bank_company_id' => 1,
                'bank_company_type' => 'spread_payment',
                'bank_company_bank_id' => 1,
                'bank_company_bank_acc_name' => 'DNY Spread',
                'bank_company_bank_acc_number' => 'SPREAD-001',
                'bank_company_bank_is_active' => 1,
            ],
            [
                'bank_company_id' => 2,
                'bank_company_type' => 'company',
                'bank_company_bank_id' => 1,
                'bank_company_bank_acc_name' => 'PT DNY',
                'bank_company_bank_acc_number' => 'COMPANY-001',
                'bank_company_bank_is_active' => 1,
            ],
        ]);
        DB::table('warehouse')->insert([
            'warehouse_id' => 1,
            'warehouse_name' => 'Warehouse Utama DNY',
            'warehouse_legal_name' => 'PT DNY',
            'warehouse_address' => 'Jakarta Selatan',
            'warehouse_phone' => '0211234567',
            'warehouse_province_id' => 31,
            'warehouse_city_id' => 3171,
            'warehouse_district_id' => 317101,
            'warehouse_subdistrict_id' => 31710101,
            'warehouse_latitude' => -6.225,
            'warehouse_longitude' => 106.8,
            'warehouse_is_active' => 1,
        ]);
        foreach (array_slice($members, 0, 2) as $member) {
            MemberBankAccount::query()->create([
                'member_bank_account_member_id' => $member->getKey(),
                'member_bank_account_bank_id' => 1,
                'member_bank_account_name' => $member->member_name,
                'member_bank_account_number' => 'BANK-'.$member->member_code,
                'member_bank_account_is_active' => 1,
                'member_bank_account_is_default' => 1,
            ]);
        }
        $category = ProductCategory::query()->create([
            'product_category_name' => 'Skincare',
            'product_category_description' => 'Produk DNY',
            'product_category_is_active' => 1,
        ]);
        $product = Product::query()->create([
            'product_product_category_id' => $category->getKey(),
            'product_code' => 'SRM-PO-001',
            'product_name' => 'Serum PO DNY',
            'product_bpom_number' => 'NA18250100004',
            'product_customer_price' => 160_000,
            'product_weight' => 100,
            'product_length' => 10,
            'product_width' => 8,
            'product_height' => 5,
            'product_unit' => 'pcs',
            'product_is_publish' => 1,
            'product_is_active' => 1,
            'product_is_deleted' => 0,
        ]);
        foreach ([1 => 100_000, 2 => 120_000, 3 => 140_000] as $levelId => $price) {
            ProductPrice::query()->create([
                'product_price_product_id' => $product->getKey(),
                'product_price_member_level_id' => $levelId,
                'product_price_value' => $price,
            ]);
        }
        DB::table('warehouse_stock')->insert([
            'warehouse_stock_warehouse_id' => 1,
            'warehouse_stock_product_id' => $product->getKey(),
            'warehouse_stock_balance' => 100,
            'warehouse_stock_transfer_in' => 0,
            'warehouse_stock_transfer_out' => 0,
        ]);
        foreach ($members as $member) {
            DB::table('member_stock')->insert([
                'member_stock_member_id' => $member->getKey(),
                'member_stock_product_id' => $product->getKey(),
                'member_stock_balance' => 0,
                'member_stock_transfer_in' => 0,
                'member_stock_transfer_out' => 0,
            ]);
        }

        return $product;
    }

    private function bankAccountId(Member $member): int
    {
        return (int) MemberBankAccount::query()
            ->where('member_bank_account_member_id', $member->getKey())
            ->value('member_bank_account_id');
    }

    private function submitPayment(
        MemberAccount $account,
        int $trxId,
        string $suffix,
        bool $hasSpreadPayment = false,
    ): void {
        if (Trx::query()->whereKey($trxId)->value('trx_status') === 'waiting_stock_screening') {
            Trx::query()->whereKey($trxId)->update([
                'trx_status' => 'waiting_payment',
                'trx_status_datetime' => now(),
            ]);
        }

        $payload = [
            'receipt_url' => "https://cdn.example.test/payment/{$suffix}.webp",
        ];
        if ($hasSpreadPayment) {
            $payload += [
                'spread_receipt_url' => "https://cdn.example.test/payment/{$suffix}-spread.webp",
            ];
        }
        $this->actingAs($account, 'member_api');
        $this->postJson("/api/v1/member/purchases/orders/{$trxId}/payment", $payload)
            ->assertOk()
            ->assertJsonPath('data.status.code', 'submitted');
    }

    private function verifyPayment(
        MemberAccount $account,
        int $trxId,
    ): void {
        $this->actingAs($account, 'member_api');
        $this->postJson("/api/v1/member/sales/orders/{$trxId}/payment/approve")
            ->assertOk()
            ->assertJsonPath('data.status.code', 'processing');
    }

    private function assertStock(Member $member, Product $product, int $balance, int $incoming, int $outgoing): void
    {
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => $member->getKey(),
            'member_stock_product_id' => $product->getKey(),
            'member_stock_balance' => $balance,
            'member_stock_transfer_in' => $incoming,
            'member_stock_transfer_out' => $outgoing,
        ]);
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
            'administrator_username' => 'preorder.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Preorder Admin',
            'administrator_email' => 'preorder.admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }
}
