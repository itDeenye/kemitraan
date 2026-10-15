<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REWARD_TOTALS = [
        'reward_point_annual' => ['reward_point_annual_total_points' => 'Total akumulasi poin tahunan'],
        'reward_point_annual_log' => ['reward_point_annual_log_points' => 'Jumlah poin mutasi'],
        'reward_point_monthly' => [
            'reward_point_monthly_total_qty' => 'Total poin bulanan',
            'reward_point_monthly_bonus_value' => 'Nominal reward poin bulanan (Rp)',
        ],
        'member_achievement' => ['member_achievement_point' => 'Total poin pada periode berjalan'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::REWARD_TOTALS as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column => $comment) {
                    $table->unsignedBigInteger($column)->default(0)->comment($comment)->change();
                }
            });
        }
        Schema::table('product', function (Blueprint $table): void {
            $table->unsignedInteger('product_monthly_points')->default(1)->comment('Poin bulanan per unit produk');
            $table->unsignedInteger('product_annual_points')->default(1)->comment('Poin tahunan per unit produk');
        });
        Schema::table('trx_detail', function (Blueprint $table): void {
            $table->unsignedInteger('trx_detail_product_monthly_points')->default(1)->comment('Snapshot poin bulanan per unit');
            $table->unsignedInteger('trx_detail_product_annual_points')->default(1)->comment('Snapshot poin tahunan per unit');
        });
        Schema::table('trx', function (Blueprint $table): void {
            $table->unsignedBigInteger('trx_total_monthly_points')->nullable()->comment('Total poin bulanan transaksi; null untuk transaksi lama');
            $table->unsignedBigInteger('trx_total_annual_points')->nullable()->comment('Total poin tahunan transaksi; null untuk transaksi lama');
        });
        Schema::table('member_point_transaction', function (Blueprint $table): void {
            $table->unsignedBigInteger('member_point_transaction_monthly_points')->nullable();
            $table->unsignedBigInteger('member_point_transaction_annual_points')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::REWARD_TOTALS as $tableName => $columns) {
            foreach ($columns as $column => $comment) {
                if (DB::table($tableName)->where($column, '>', 4294967295)->exists()) {
                    throw new RuntimeException("Rollback tidak aman: {$column} melebihi kapasitas integer lama.");
                }
            }
        }
        Schema::table('member_point_transaction', function (Blueprint $table): void {
            $table->dropColumn(['member_point_transaction_monthly_points', 'member_point_transaction_annual_points']);
        });
        Schema::table('trx', function (Blueprint $table): void {
            $table->dropColumn(['trx_total_monthly_points', 'trx_total_annual_points']);
        });
        Schema::table('trx_detail', function (Blueprint $table): void {
            $table->dropColumn(['trx_detail_product_monthly_points', 'trx_detail_product_annual_points']);
        });
        Schema::table('product', function (Blueprint $table): void {
            $table->dropColumn(['product_monthly_points', 'product_annual_points']);
        });
        foreach (self::REWARD_TOTALS as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column => $comment) {
                    $table->unsignedInteger($column)->default(0)->comment($comment)->change();
                }
            });
        }
    }
};
