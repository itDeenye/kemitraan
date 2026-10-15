<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductPointMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_rollback_refuses_to_truncate_a_large_annual_point_balance(): void
    {
        DB::table('reward_point_annual')->insert([
            'reward_point_annual_member_id' => 1,
            'reward_point_annual_year' => 2026,
            'reward_point_annual_total_points' => 4294967296,
        ]);
        $migration = require database_path('migrations/2026_10_14_161505_add_reward_points_to_products_and_transactions.php');

        $rollbackException = null;
        try {
            $migration->down();
        } catch (\RuntimeException $exception) {
            $rollbackException = $exception;
        }
        $this->assertInstanceOf(\RuntimeException::class, $rollbackException);
        $this->assertStringContainsString('reward_point_annual_total_points', $rollbackException->getMessage());
        $this->assertTrue(Schema::hasColumn('product', 'product_monthly_points'));
        $this->assertSame(4294967296, (int) DB::table('reward_point_annual')->value('reward_point_annual_total_points'));
    }
}
