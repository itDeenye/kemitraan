<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Member;
use App\Models\SiteAdministrator;
use App\Models\Trx;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminMemberRankingReportTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/reports/member-rankings';

    private function admin(): void
    {
        $this->seed(AccessControlSeeder::class);
        $this->actingAs(SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => 1,
            'administrator_username' => 'ranking.test',
            'administrator_password' => Hash::make('test-password'),
            'administrator_name' => 'Ranking Test',
            'administrator_email' => 'ranking@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]), 'admin_api');
    }

    private function member(int $level, string $code, string $name): int
    {
        return Member::query()->create([
            'member_member_level_id' => $level,
            'member_code' => $code,
            'member_name' => $name,
            'member_status' => 1,
            'member_gender' => 'Perempuan',
            'member_join_datetime' => '2026-10-01 00:00:00',
        ])->getKey();
    }

    private function purchase(int $member, int $amount, string $status, string $date, bool $po = false, string $type = 'distributor'): void
    {
        Trx::query()->create([
            'trx_code' => 'REPORT-'.(Trx::query()->count() + 1),
            'trx_buyer_type' => $type,
            'trx_buyer_id' => $member,
            'trx_seller_type' => 'warehouse',
            'trx_seller_id' => 1,
            'trx_type' => 'stock',
            'trx_is_preorder' => $po,
            'trx_total_price' => $amount,
            'trx_grand_total_nett_price' => $amount,
            'trx_status' => $status,
            'trx_status_datetime' => $date,
            'trx_datetime' => $date,
        ]);
    }

    private function registration(int $submitter, int $upline, int $recruited, string $status, string $approvedAt): void
    {
        DB::table('member_registration')->insert([
            'member_registration_member_level_id' => 3,
            'member_registration_submitter_member_id' => $submitter,
            'member_registration_upline_member_id' => $upline,
            'member_registration_member_id' => $recruited,
            'member_registration_username' => '',
            'member_registration_password' => '',
            'member_registration_status' => $status,
            'member_registration_datetime' => '2026-09-29 10:00:00',
            'member_registration_status_datetime' => $approvedAt,
        ]);
    }

    private function report(string $report, array $params = []): TestResponse
    {
        return $this->getJson(self::ENDPOINT.'?'.http_build_query(array_replace([
            'report' => $report,
            'year' => 2026,
            'month' => 10,
        ], $params)));
    }

    public function test_report_requires_admin_and_valid_period(): void
    {
        $this->getJson(self::ENDPOINT)->assertUnauthorized();
        $this->admin();
        $this->report('purchases', ['month' => null])->assertUnprocessable()->assertJsonValidationErrors('month');
        $this->report('recruitment', ['month' => 13])->assertUnprocessable()->assertJsonValidationErrors('month');
        $this->report('missing')->assertUnprocessable()->assertJsonValidationErrors('report');
        $this->report('annual-points', ['month' => null])->assertOk();
    }

    public function test_purchases_rank_eligible_regular_and_po_transactions_and_export_all_filtered_rows(): void
    {
        $this->admin();
        $first = $this->member(1, '0001/0000/0000', 'Citra Pagi');
        $second = $this->member(1, '0002/0000/0000', 'Citra Siang');
        $agent = $this->member(2, '0001/0001/0000', 'Agen Sore');
        DB::table('ref_city')->insert(['city_id' => '3578', 'city_province_id' => '35', 'city_name' => 'Surabaya', 'city_type' => 'Kota']);
        DB::table('member_address')->insert([
            ['member_address_member_id' => $first, 'member_address_city_id' => '3578', 'member_address_is_default' => 1],
            ['member_address_member_id' => $first, 'member_address_city_id' => '0000', 'member_address_is_default' => 1],
        ]);
        $this->purchase($first, 100, 'processing', '2026-10-01 00:00:00');
        $this->purchase($first, 75, 'completed', '2026-10-31 23:59:59', true);
        $this->purchase($first, 800, 'cancelled', '2026-10-10 10:00:00');
        $this->purchase($first, 900, 'completed', '2026-11-01 00:00:00');
        $this->purchase($second, 90, 'shipped', '2026-10-02 00:00:00');
        $this->purchase($agent, 200, 'received', '2026-10-06 10:00:00', type: 'agent');

        $this->report('purchases')->assertOk()
            ->assertJsonCount(3, 'data.results')
            ->assertJsonPath('data.results.0.id', $first)
            ->assertJsonPath('data.results.0.purchase_amount', 175)
            ->assertJsonPath('data.results.0.area', 'Kota Surabaya')
            ->assertJsonPath('data.results.1.id', $second)
            ->assertJsonPath('data.results.2.id', $agent);

        $this->report('purchases', ['search' => 'Citra', 'limit' => 1])->assertOk()
            ->assertJsonCount(1, 'data.results');
        $this->report('purchases', ['search' => 'Citra', 'limit' => 1, 'pagination' => 'false'])->assertOk()
            ->assertJsonCount(2, 'data.results')
            ->assertJsonPath('data.results.0.code', '0001/0000/0000')
            ->assertJsonPath('data.results.1.area', '-');
    }

    public function test_recruitment_counts_approved_distinct_members_for_submitter_not_upline(): void
    {
        $this->admin();
        $upline = $this->member(1, '0010/0000/0000', 'Distributor');
        $submitter = $this->member(2, '0010/0001/0000', 'Pendaftar');
        $this->registration($submitter, $upline, 200, 'approved', '2026-10-01 00:00:00');
        $this->registration($submitter, $upline, 200, 'approved', '2026-10-02 10:00:00');
        $this->registration($submitter, $upline, 201, 'approved', '2026-10-03 10:00:00');
        $this->registration($submitter, $upline, 202, 'rejected', '2026-10-04 10:00:00');
        $this->registration($submitter, $upline, 203, 'approved', '2026-11-01 00:00:00');
        $this->registration(0, $upline, 204, 'approved', '2026-10-10 10:00:00');

        $this->report('recruitment')->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $submitter)
            ->assertJsonPath('data.results.0.recruit_count', 2);
    }

    public function test_reward_reports_use_closing_rows_and_annual_totals_for_the_selected_period(): void
    {
        $this->admin();
        $distributor = $this->member(1, '0010/0000/0000', 'Distributor');
        $formerlyDistributor = $this->member(2, '0020/0001/0000', 'Eks Distributor');
        $zeroBonus = $this->member(1, '0030/0000/0000', 'Tanpa Bonus');
        foreach ([
            [$distributor, 1, 0, 10, 35, 120000],
            [$formerlyDistributor, 1, 0, 10, 45, 180000],
            [$zeroBonus, 1, 0, 10, 20, 0],
            [$distributor, 1, 0, 11, 90, 500000],
            [$distributor, 1, $zeroBonus, 9, 95, 600000],
        ] as [$member, $level, $upline, $month, $points, $bonus]) {
            DB::table('reward_point_monthly')->insert([
                'reward_point_monthly_member_id' => $member,
                'reward_point_monthly_member_level_id' => $level,
                'reward_point_monthly_upline_id' => $upline,
                'reward_point_monthly_year' => 2026,
                'reward_point_monthly_month' => $month,
                'reward_point_monthly_total_qty' => $points,
                'reward_point_monthly_bonus_value' => $bonus,
            ]);
        }
        foreach ([[$distributor, 2026, 900], [$distributor, 2025, 1500], [$formerlyDistributor, 2026, 1200]] as [$member, $year, $points]) {
            DB::table('reward_point_annual')->insert([
                'reward_point_annual_member_id' => $member,
                'reward_point_annual_year' => $year,
                'reward_point_annual_total_points' => $points,
            ]);
        }

        $this->report('monthly-rewards')->assertOk()
            ->assertJsonCount(2, 'data.results')
            ->assertJsonPath('data.results.0.id', $formerlyDistributor)
            ->assertJsonPath('data.results.0.level_code', 'DST')
            ->assertJsonPath('data.results.0.monthly_points', 45)
            ->assertJsonPath('data.results.0.reward_amount', 180000);
        $this->report('monthly-rewards', ['month' => 9])->assertOk()->assertJsonCount(0, 'data.results');

        $this->report('annual-points', ['month' => null])->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $distributor)
            ->assertJsonPath('data.results.0.annual_points', 900);
    }

    public function test_four_report_links_are_seeded_under_laporan(): void
    {
        $this->seed(AccessControlSeeder::class);
        $parent = DB::table('site_administrator_menu')->where('administrator_menu_title', 'Laporan')
            ->where('administrator_menu_par_id', 0)->value('administrator_menu_id');
        $this->assertNotNull($parent);
        $this->assertSame([
            '/report/member-rankings/purchases',
            '/report/member-rankings/recruitment',
            '/report/member-rankings/monthly-rewards',
            '/report/member-rankings/annual-points',
        ], DB::table('site_administrator_menu')->where('administrator_menu_par_id', $parent)
            ->orderBy('administrator_menu_order_by')->pluck('administrator_menu_link')->all());
    }

    public function test_migration_reuses_the_existing_parent_and_preserves_role_scope(): void
    {
        $this->seed(AccessControlSeeder::class);
        $migration = require database_path('migrations/2026_11_16_130528_add_member_ranking_reports_admin_menu.php');
        $menus = DB::table('site_administrator_menu');
        $privileges = DB::table('site_administrator_privilege');
        $migration->down();
        $parent = (clone $menus)->insertGetId([
            'administrator_menu_title' => 'Laporan',
            'administrator_menu_par_id' => 0,
        ]);
        $salesMenu = (clone $menus)->where('administrator_menu_link', '/partnership/sales-report')->value('administrator_menu_id');
        $recruitmentMenu = (clone $menus)->where('administrator_menu_link', '/partnership/members')->value('administrator_menu_id');
        (clone $privileges)->where('administrator_privilege_administrator_menu_id', $recruitmentMenu)
            ->where('administrator_privilege_administrator_group_id', 2)->delete();

        $migration->up();

        $this->assertSame(4, (clone $menus)->where('administrator_menu_par_id', $parent)->count());
        $purchases = (clone $menus)->where('administrator_menu_link', '/report/member-rankings/purchases')->value('administrator_menu_id');
        $recruitment = (clone $menus)->where('administrator_menu_link', '/report/member-rankings/recruitment')->value('administrator_menu_id');
        $this->assertDatabaseHas('site_administrator_privilege', [
            'administrator_privilege_administrator_menu_id' => $purchases,
            'administrator_privilege_administrator_group_id' => 2,
        ]);
        $this->assertDatabaseMissing('site_administrator_privilege', [
            'administrator_privilege_administrator_menu_id' => $recruitment,
            'administrator_privilege_administrator_group_id' => 2,
        ]);

        $migration->down();

        $this->assertDatabaseHas('site_administrator_menu', ['administrator_menu_id' => $parent]);
        $this->assertDatabaseHas('site_administrator_menu', ['administrator_menu_id' => $salesMenu]);
        $this->assertSame(0, (clone $menus)->where('administrator_menu_par_id', $parent)->count());
    }
}
