<?php

namespace Tests\Feature\Api\V1\Member;

use App\Mail\PaymentExpiredMail;
use App\Mail\StockScreeningApprovedMail;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\RewardStockist;
use App\Models\Trx;
use App\Models\TrxPaymentTransfer;
use App\Services\Dashboard\AdminDashboardService;
use App\Services\Inventory\StockAllocationService;
use App\Services\Inventory\StockScreeningService;
use App\Services\Partnership\MemberTransactionCancellationService;
use App\Services\Purchase\PaymentUploadExpiryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentUploadExpiryTest extends TestCase
{
    use RefreshDatabase;

    private MemberAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        config(['orders.payment_upload_timeout_minutes' => 1440]);
        $this->travelTo(Carbon::parse('2026-11-23 09:00:00', 'Asia/Jakarta'));
        Mail::fake();
        $member = Member::query()->create([
            'member_member_level_id' => 1, 'member_code' => '0001/0000/0000',
            'member_name' => 'Distributor Uji', 'member_email' => 'expiry@example.test',
            'member_status' => 1, 'member_join_datetime' => now(),
        ]);
        $group = MemberGroup::query()->create([
            'member_group_name' => 'Mitra', 'member_group_description' => '', 'member_group_is_active' => 1,
        ]);
        $this->account = MemberAccount::query()->create([
            'member_account_member_id' => $member->getKey(), 'member_account_member_group_id' => $group->getKey(),
            'member_account_username' => 'expiry.test', 'member_account_password' => Hash::make('Password123'),
            'member_account_is_active' => 1,
        ]);
    }

    private function order(array $attributes = [], string $paymentStatus = 'pending'): Trx
    {
        $trx = Trx::query()->create(array_replace([
            'trx_code' => 'EXPIRY-'.(Trx::query()->count() + 1),
            'trx_type' => 'stock', 'trx_is_preorder' => false,
            'trx_seller_type' => 'warehouse', 'trx_seller_id' => 1,
            'trx_buyer_type' => 'distributor', 'trx_buyer_id' => $this->account->member_account_member_id,
            'trx_total_price' => 100000, 'trx_bill_amount' => 100000,
            'trx_grand_total_nett_price' => 100000, 'trx_shipping_method' => 'pickup',
            'trx_status' => 'waiting_payment', 'trx_status_datetime' => now(), 'trx_datetime' => now(),
            'trx_payment_upload_timeout_enabled' => true, 'trx_payment_upload_expires_at' => now()->addMinutes(1440),
        ], $attributes));
        TrxPaymentTransfer::query()->create([
            'payment_transfer_trx_id' => $trx->getKey(), 'payment_transfer_bill_amount' => 100000,
            'payment_transfer_amount' => 0,
            'payment_transfer_note' => '',
            'payment_transfer_datetime' => now(), 'payment_transfer_approval_status' => $paymentStatus,
        ]);

        return $trx;
    }

    public function test_deadline_starts_after_screening_and_legacy_and_po_are_untouched(): void
    {
        $trx = $this->order(['trx_status' => 'waiting_stock_screening', 'trx_payment_upload_expires_at' => null]);
        $this->travel(2)->days();
        $approved = app(StockScreeningService::class)->approve($trx);
        $this->assertTrue($approved->trx_payment_upload_expires_at->equalTo(now()->addMinutes(1440)));
        (new StockScreeningApprovedMail($approved))->assertSeeInHtml('Unggah bukti transfer paling lambat');
        $this->actingAs($this->account, 'member_api')
            ->getJson("/api/v1/member/purchases/orders/{$trx->getKey()}")
            ->assertOk()->assertJsonPath('data.payment_upload_expires_at', now()->addMinutes(1440)->toAtomString())
            ->assertJsonPath('data.actions.can_upload_payment', true);

        $legacy = $this->order(['trx_status' => 'waiting_stock_screening', 'trx_payment_upload_timeout_enabled' => false, 'trx_payment_upload_expires_at' => null]);
        $this->assertNull(app(StockScreeningService::class)->approve($legacy)->trx_payment_upload_expires_at);
        $po = $this->order(['trx_status' => 'waiting_stock_screening', 'trx_is_preorder' => true, 'trx_payment_upload_expires_at' => null]);
        $this->assertNull(app(StockScreeningService::class)->approve($po)->trx_payment_upload_expires_at);
    }

    public static function configuredDurations(): array
    {
        return ['one minute' => [1], 'one hour' => [60], 'one day' => [1440]];
    }

    #[DataProvider('configuredDurations')]
    public function test_configured_minutes_control_the_exact_expiration_deadline(int $minutes): void
    {
        config(['orders.payment_upload_timeout_minutes' => $minutes]);
        $trx = $this->order(['trx_status' => 'waiting_stock_screening', 'trx_payment_upload_expires_at' => null]);
        $approved = app(StockScreeningService::class)->approve($trx);
        $deadline = now()->addMinutes($minutes);
        $this->assertTrue($approved->trx_payment_upload_expires_at->equalTo($deadline));
        $this->actingAs($this->account, 'member_api')
            ->getJson("/api/v1/member/purchases/orders/{$trx->getKey()}")
            ->assertOk()->assertJsonPath('data.payment_upload_expires_at', $deadline->toAtomString());

        $this->travelTo($deadline->copy()->subSecond());
        $this->assertFalse(app(PaymentUploadExpiryService::class)->expire((int) $trx->getKey()));
        config(['orders.payment_upload_timeout_minutes' => $minutes + 100]);
        $this->travel(1)->seconds();
        $this->assertTrue(app(PaymentUploadExpiryService::class)->expire((int) $trx->getKey()));
        $this->assertSame('expired', $trx->refresh()->trx_status);
        Mail::assertQueued(PaymentExpiredMail::class);
    }

    public static function environmentDurations(): array
    {
        return [
            'missing' => [null, 1440], 'one minute' => ['1', 1], 'one hour' => ['60', 60],
            'one day' => ['1440', 1440], 'empty' => ['', 1440], 'zero' => ['0', 1440],
            'negative' => ['-1', 1440], 'invalid' => ['invalid', 1440], 'fraction' => ['1.5', 1440],
            'true' => ['true', 1440], 'false' => ['false', 1440], 'null' => ['null', 1440],
        ];
    }

    #[DataProvider('environmentDurations')]
    public function test_environment_configuration_defaults_to_twenty_four_hours_for_missing_or_invalid_values(?string $value, int $expected): void
    {
        $key = 'DNY_PAYMENT_UPLOAD_TIMEOUT_MINUTES';
        $previousEnv = $_ENV[$key] ?? null;
        $previousServer = $_SERVER[$key] ?? null;
        $previousProcess = getenv($key);
        try {
            if ($value === null) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
            } else {
                $_ENV[$key] = $_SERVER[$key] = $value;
                putenv($key.'='.$value);
            }
            $configuration = require config_path('orders.php');
            $this->assertSame($expected, $configuration['payment_upload_timeout_minutes']);
        } finally {
            if ($previousEnv === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $previousEnv;
            }
            if ($previousServer === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $previousServer;
            }
            putenv($previousProcess === false ? $key : $key.'='.$previousProcess);
        }
    }

    public function test_expiry_releases_stock_and_voucher_once_and_queues_one_email(): void
    {
        $trx = $this->order();
        $trx->details()->create([
            'trx_detail_product_id' => 42, 'trx_detail_product_code' => 'EXP-42',
            'trx_detail_product_name' => 'Produk Uji', 'trx_detail_qty' => 1,
        ]);
        DB::table('warehouse_stock')->insert([
            'warehouse_stock_warehouse_id' => 1, 'warehouse_stock_product_id' => 42, 'warehouse_stock_balance' => 2,
        ]);
        app(StockAllocationService::class)->reserve($trx);
        $voucher = RewardStockist::query()->create([
            'reward_stockist_member_id' => $this->account->member_account_member_id,
            'reward_stockist_year' => 2026, 'reward_stockist_month' => 11,
            'reward_stockist_used_trx_id' => $trx->getKey(), 'reward_stockist_used_value' => 100,
            'reward_stockist_bonus_value' => 100, 'reward_stockist_created_datetime' => now(),
        ]);
        $this->travel(1440)->minutes();
        $this->artisan('orders:expire-unpaid-distributor')->assertSuccessful();
        $this->artisan('orders:expire-unpaid-distributor')->assertSuccessful();
        $this->assertSame('expired', $trx->refresh()->trx_status);
        $this->assertSame(0, $voucher->refresh()->reward_stockist_used_trx_id);
        $this->assertDatabaseHas('warehouse_stock', [
            'warehouse_stock_product_id' => 42, 'warehouse_stock_balance' => 2, 'warehouse_stock_transfer_out' => 0,
        ]);
        $this->assertDatabaseHas('member_stock', [
            'member_stock_member_id' => $trx->trx_buyer_id, 'member_stock_product_id' => 42,
            'member_stock_balance' => 0, 'member_stock_transfer_in' => 0,
        ]);
        Mail::assertQueued(PaymentExpiredMail::class, fn ($mail) => $mail->hasTo('expiry@example.test')
            && $mail->orderNumber === $trx->trx_code);
        Mail::assertQueuedCount(1);
        $this->assertSame(1, DB::table('notification')->where('notification_title', 'Pesanan Kedaluwarsa')->count());
        $this->assertSame(0, app(MemberTransactionCancellationService::class)->summary($this->account->member)['active']);
        $this->actingAs($this->account, 'member_api');
        $this->getJson('/api/v1/member/purchases/orders?filter[status]=expired')->assertOk()
            ->assertJsonPath('data.results.0.status.label', 'Kedaluwarsa')
            ->assertJsonPath('data.results.0.actions.can_upload_payment', false);
        $this->getJson('/api/v1/member/purchases/orders/summary')->assertOk()
            ->assertJsonPath('data.purchases.cancelled', 1)
            ->assertJsonPath('data.purchases.action_required', 0);
        $analytics = app(AdminDashboardService::class)->analytics(['date_from' => '2026-11-01', 'date_to' => '2026-11-30']);
        $this->assertSame(0, $analytics['summary']['turnover']);
        $this->assertSame(0, $analytics['summary']['active_orders']);
    }

    public function test_upload_at_deadline_commits_expiry_even_when_scheduler_has_not_run(): void
    {
        $trx = $this->order();
        $this->travel(1440)->minutes();
        $this->actingAs($this->account, 'member_api')
            ->postJson("/api/v1/member/purchases/orders/{$trx->getKey()}/payment", ['receipt_url' => 'https://cdn.example.test/payment/proof.png'])
            ->assertUnprocessable()->assertJsonPath('message', 'Pesanan kedaluwarsa karena batas waktu upload bukti transfer telah berakhir.');
        $this->assertSame('expired', $trx->refresh()->trx_status);
        $this->assertSame('pending', $trx->paymentTransfer->payment_transfer_approval_status);
        Mail::assertQueuedCount(1);
    }

    public function test_timely_upload_remains_under_verification_after_deadline(): void
    {
        $trx = $this->order();
        $this->travelTo($trx->trx_payment_upload_expires_at->copy()->subSecond());
        $this->actingAs($this->account, 'member_api')
            ->postJson("/api/v1/member/purchases/orders/{$trx->getKey()}/payment", ['receipt_url' => 'https://cdn.example.test/payment/proof.png'])
            ->assertOk()->assertJsonPath('data.status.code', 'submitted');
        $this->travel(3)->days();
        $this->artisan('orders:expire-unpaid-distributor')->assertSuccessful();
        $this->assertSame('waiting_payment_approval', $trx->refresh()->trx_status);
        Mail::assertNotQueued(PaymentExpiredMail::class);
    }

    public function test_another_member_cannot_expire_the_owners_order_through_upload(): void
    {
        $trx = $this->order(['trx_buyer_id' => 999]);
        $this->travel(25)->hours();
        $this->actingAs($this->account, 'member_api')
            ->postJson("/api/v1/member/purchases/orders/{$trx->getKey()}/payment", ['receipt_url' => 'https://cdn.example.test/payment/proof.png'])
            ->assertNotFound();
        $this->assertSame('waiting_payment', $trx->refresh()->trx_status);
        Mail::assertNothingQueued();
    }

    public function test_rolled_back_expiry_does_not_send_a_notification(): void
    {
        $trx = $this->order();
        $this->travel(25)->hours();
        try {
            DB::transaction(function () use ($trx): void {
                app(PaymentUploadExpiryService::class)->expire((int) $trx->getKey());
                throw new \RuntimeException('rollback expiry');
            });
            $this->fail('The transaction should have rolled back.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('rollback expiry', $exception->getMessage());
        }
        $this->assertSame('waiting_payment', $trx->refresh()->trx_status);
        Mail::assertNothingQueued();
        $this->assertDatabaseMissing('notification', ['notification_title' => 'Pesanan Kedaluwarsa']);
    }

    public static function excludedOrders(): array
    {
        return [
            'PO' => [['trx_is_preorder' => true], 'pending'],
            'agent' => [['trx_buyer_type' => 'agent'], 'pending'],
            'member seller' => [['trx_seller_type' => 'distributor'], 'pending'],
            'legacy' => [['trx_payment_upload_timeout_enabled' => false], 'pending'],
            'screening' => [['trx_status' => 'waiting_stock_screening'], 'pending'],
            'cancelled' => [['trx_status' => 'cancelled'], 'pending'],
            'verified' => [['trx_status' => 'processing'], 'approved'],
            'submitted' => [[], 'submitted'],
            'not due' => [['trx_payment_upload_expires_at' => '2026-12-01 00:00:00'], 'pending'],
        ];
    }

    #[DataProvider('excludedOrders')]
    public function test_ineligible_orders_do_not_expire(array $attributes, string $paymentStatus): void
    {
        $trx = $this->order($attributes, $paymentStatus);
        $before = $trx->trx_status;
        $this->travel(25)->hours();
        $this->assertFalse(app(PaymentUploadExpiryService::class)->expire((int) $trx->getKey()));
        $this->assertSame($before, $trx->refresh()->trx_status);
        Mail::assertNothingQueued();
    }

    public function test_expired_email_renders_reason_and_order_code(): void
    {
        $mail = new PaymentExpiredMail('Distributor Uji', 'EXPIRY-123', '24 November 2026 09:00', 'https://example.test/member/transactions/orders/1');
        $mail->assertSeeInHtml('EXPIRY-123');
        $mail->assertSeeInHtml('batas waktu upload berakhir');
        $mail->assertSeeInHtml('Kedaluwarsa');
    }
}
