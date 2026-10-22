<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Exceptions\ProcessException;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use App\Models\Trx;
use App\Services\Purchase\MemberPurchaseService;
use App\Services\Sales\MemberSalesService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MemberDirectLevelChangeTest extends TestCase
{
    use RefreshDatabase;

    private function setupMember(): Member
    {
        $this->seed(AccessControlSeeder::class);
        Http::preventStrayRequests();
        $group = SiteAdministratorGroup::query()->create(['administrator_group_title' => 'Admin', 'administrator_group_type' => 'superuser', 'administrator_group_is_active' => 1]);
        $admin = SiteAdministrator::query()->create(['administrator_administrator_group_id' => $group->getKey(),
            'administrator_username' => 'test.admin', 'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Admin', 'administrator_email' => 'admin@example.test', 'administrator_is_active' => 1]);
        $this->actingAs($admin, 'admin_api');
        $member = Member::query()->create(['member_code' => '0001/0001/0001', 'member_member_level_id' => 3,
            'member_parent_member_id' => 0, 'member_name' => 'Member', 'member_email' => 'member@example.test',
            'member_mobilephone' => '+6281234567890', 'member_identity_no' => '0001234567890123', 'member_status' => 1,
            'member_supply_chain_code' => 'CUST-EXISTING', 'member_join_datetime' => now()]);
        MemberAccount::query()->create(['member_account_member_id' => $member->getKey(), 'member_account_member_group_id' => 3,
            'member_account_username' => $member->member_code, 'member_account_password' => Hash::make('Secret123')]);

        return $member;
    }

    public function test_direct_correction_keeps_identifiers_and_updates_level_access_and_audit(): void
    {
        $member = $this->setupMember();
        $url = "/api/v1/admin/partnership/members/{$member->getKey()}";
        $this->getJson($url)->assertOk()->assertJsonPath('data.can_change_level', true);
        $this->getJson($url.'/level-change-options')->assertOk()->assertJsonPath('data.can_change_level', true);
        $this->postJson($url.'/change-level', ['level_id' => 1, 'upline_member_id' => null, 'note' => 'Koreksi registrasi'])
            ->assertOk()->assertJsonPath('data.level.code', 'DST')->assertJsonPath('data.customer_number', 'CUST-EXISTING')
            ->assertJsonPath('data.code', '0001/0001/0001')->assertJsonPath('data.username', '0001/0001/0001');
        $this->assertSame(1, (int) $member->accounts()->sole()->member_account_member_group_id);
        $this->assertDatabaseHas('member_history', ['member_history_member_id' => $member->getKey(),
            'member_history_from_level_id' => 3, 'member_history_to_level_id' => 1, 'member_history_action' => 'level_correction',
            'member_history_reason' => 'Koreksi registrasi']);
        $this->postJson($url.'/change-level', ['level_id' => 1, 'note' => 'Retry'])->assertUnprocessable();
        $this->assertDatabaseCount('member_history', 1);
        Http::assertNothingSent();
    }

    #[DataProvider('transactionCases')]
    public function test_every_transaction_state_blocks_correction_without_customer_or_warehouse_id_collisions(string $side, string $status): void
    {
        $member = $this->setupMember();
        Trx::query()->create(['trx_code' => 'TRX-TEST', 'trx_buyer_type' => $side === 'buyer' ? 'reseller' : 'customer',
            'trx_buyer_id' => $member->getKey(), 'trx_seller_type' => $side === 'seller' ? 'reseller' : 'warehouse',
            'trx_seller_id' => $member->getKey(), 'trx_status' => $status, 'trx_datetime' => now(),
            'trx_type' => 'stock', 'trx_total_price' => 10000, 'trx_status_datetime' => now()]);
        $url = "/api/v1/admin/partnership/members/{$member->getKey()}";
        $allowed = $side === 'unrelated';
        $this->getJson($url.'/level-change-options')->assertOk()->assertJsonPath('data.can_change_level', $allowed);
        $response = $this->postJson($url.'/change-level', ['level_id' => 1, 'note' => 'Koreksi']);
        $allowed ? $response->assertOk() : $response->assertUnprocessable();
        if (! $allowed) {
            $this->assertSame(3, (int) $member->fresh()->member_member_level_id);
            $this->assertDatabaseCount('member_history', 0);
        }
    }

    public static function transactionCases(): array
    {
        return [['buyer', 'waiting_payment'], ['buyer', 'cancelled'], ['buyer', 'completed'],
            ['seller', 'rejected'], ['seller', 'completed'], ['unrelated', 'completed']];
    }

    public function test_downline_blocks_correction_even_when_inactive(): void
    {
        $member = $this->setupMember();
        Member::query()->create(['member_code' => '0002/0000/0001', 'member_name' => 'Child', 'member_member_level_id' => 3,
            'member_parent_member_id' => $member->getKey(), 'member_status' => 0, 'member_join_datetime' => now()]);
        $url = "/api/v1/admin/partnership/members/{$member->getKey()}";
        $this->getJson($url)->assertOk()->assertJsonPath('data.can_change_level', false);
        $this->postJson($url.'/change-level', ['level_id' => 1, 'note' => 'Koreksi'])->assertUnprocessable();
    }

    #[DataProvider('emptyReasons')]
    public function test_correction_without_a_reason_still_records_the_admin_and_level_history(array $optionalReason): void
    {
        $member = $this->setupMember();
        $administrator = auth('admin_api')->user();
        $this->postJson("/api/v1/admin/partnership/members/{$member->getKey()}/change-level", [
            'level_id' => 1, 'upline_member_id' => null, ...$optionalReason,
        ])->assertOk()->assertJsonPath('data.level.code', 'DST');
        $history = $member->histories()->sole();
        $this->assertSame('level_correction', $history->member_history_action);
        $this->assertSame(3, (int) $history->member_history_from_level_id);
        $this->assertSame(1, (int) $history->member_history_to_level_id);
        $this->assertSame((int) $administrator->getKey(), (int) $history->member_history_approved_by);
        $this->assertNotEmpty($history->member_history_reason);
        Http::assertNothingSent();
    }

    public static function emptyReasons(): array
    {
        return ['omitted' => [[]], 'null' => [['note' => null]], 'empty' => [['note' => '']], 'whitespace' => [['note' => '   ']]];
    }

    public function test_invalid_placement_does_not_change_member_without_a_reason(): void
    {
        $member = $this->setupMember();
        $url = "/api/v1/admin/partnership/members/{$member->getKey()}/change-level";
        $this->postJson($url, ['level_id' => 2, 'upline_member_id' => $member->getKey()])->assertUnprocessable();
        $this->postJson($url, ['level_id' => 999])->assertUnprocessable();
        $this->assertSame(3, (int) $member->fresh()->member_member_level_id);
        $this->assertDatabaseCount('member_history', 0);
    }

    public function test_level_change_requires_admin_authentication(): void
    {
        $this->getJson('/api/v1/admin/partnership/members/1/level-change-options')->assertUnauthorized();
        $this->postJson('/api/v1/admin/partnership/members/1/change-level', ['level_id' => 1, 'note' => 'Test'])->assertUnauthorized();
    }

    #[DataProvider('checkoutServices')]
    public function test_checkout_cannot_use_a_stale_level_during_correction(string $service): void
    {
        $member = $this->setupMember();
        $account = $member->accounts()->sole()->load('member.level');
        $member->update(['member_member_level_id' => 1]);
        $this->expectException(ProcessException::class);
        $this->expectExceptionMessage('Level atau jaringan mitra berubah.');
        app($service)->checkout($account, []);
    }

    public static function checkoutServices(): array
    {
        return [[MemberPurchaseService::class], [MemberSalesService::class]];
    }
}
