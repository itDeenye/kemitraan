<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->alignRewardPointMonthlyTable();

        Schema::dropIfExists('reward_point_monthly_log');
        Schema::dropIfExists('reward_voucher_monthly_log');
        Schema::dropIfExists('reward_voucher_monthly');
        Schema::dropIfExists('x_reward_voucher_monthly_log');
        Schema::dropIfExists('x_reward_voucher_monthly');

        $this->createRewardStockistTable();
    }

    public function down(): void
    {
        throw new RuntimeException('Rekonsiliasi reward bersifat destruktif dan harus dibatalkan melalui migration lanjutan.');
    }

    private function createRewardStockistTable(): void
    {
        if (Schema::hasTable('reward_stockist')) {
            return;
        }

        Schema::create('reward_stockist', function (Blueprint $table) {
            $table->comment('Menyimpan data saldo voucher belanja bulanan mitra DNY Skincare per periode bulan (didapat dari reward stokis dll).');
            $table->increments('reward_stockist_id')->comment('ID Voucher Bulanan');
            $table->unsignedInteger('reward_stockist_member_id')->comment('ID Member');
            $table->unsignedSmallInteger('reward_stockist_year')->comment('Tahun Berjalan (YYYY)');
            $table->unsignedTinyInteger('reward_stockist_month')->comment('Bulan Berjalan (1-12)');
            $table->unsignedInteger('reward_stockist_total_trx_amount')->default(0)->comment('Total saldo voucher bulanan yang didapat (Rp)');
            $table->unsignedInteger('reward_stockist_bonus_value')->default(0)->comment('Total saldo voucher bulanan yang sudah dibelanjakan (Rp)');
            $table->unsignedInteger('reward_stockist_used_value')->default(0)->comment('Total saldo voucher bulanan yang hangus/expired (Rp)');
            $table->unsignedInteger('reward_stockist_used_trx_id');
            $table->date('reward_stockist_expiry_date')->nullable()->comment('Tanggal batas akhir klaim/penggunaan (akhir bulan berikutnya)');
            $table->dateTime('reward_stockist_created_datetime')->nullable()->comment('Waktu update terakhir');

            $table->unique(
                ['reward_stockist_member_id', 'reward_stockist_year', 'reward_stockist_month'],
                'idx_rev_voucher_monthly_uniq'
            );
            $table->index(
                ['reward_stockist_year', 'reward_stockist_month'],
                'idx_rev_voucher_monthly_period'
            );
        });
    }

    private function alignRewardPointMonthlyTable(): void
    {
        if (! Schema::hasTable('reward_point_monthly')) {
            return;
        }

        Schema::whenTableDoesntHaveColumn(
            'reward_point_monthly',
            'reward_point_monthly_upline_id',
            fn (Blueprint $table) => $table->unsignedInteger('reward_point_monthly_upline_id')->default(0)->after('reward_point_monthly_id')
        );
        Schema::whenTableDoesntHaveColumn(
            'reward_point_monthly',
            'reward_point_monthly_upline_level_id',
            fn (Blueprint $table) => $table->unsignedInteger('reward_point_monthly_upline_level_id')->default(0)->after('reward_point_monthly_upline_id')
        );
        Schema::whenTableDoesntHaveColumn(
            'reward_point_monthly',
            'reward_point_monthly_member_level_id',
            fn (Blueprint $table) => $table->unsignedInteger('reward_point_monthly_member_level_id')->after('reward_point_monthly_member_id')
        );
        Schema::whenTableDoesntHaveColumn(
            'reward_point_monthly',
            'reward_point_monthly_bonus_value',
            fn (Blueprint $table) => $table->unsignedInteger('reward_point_monthly_bonus_value')->default(0)->after('reward_point_monthly_total_qty')
        );
        Schema::whenTableDoesntHaveColumn(
            'reward_point_monthly',
            'reward_point_monthly_admin_id',
            fn (Blueprint $table) => $table->unsignedInteger('reward_point_monthly_admin_id')->default(0)->after('reward_point_monthly_bonus_value')
        );

        foreach ([
            'reward_point_monthly_total_amount',
            'reward_point_monthly_reward_qty_value',
            'reward_point_monthly_reward_stockist_value',
            'reward_point_monthly_reward_voucher_monthly_id',
        ] as $obsoleteColumn) {
            Schema::whenTableHasColumn(
                'reward_point_monthly',
                $obsoleteColumn,
                fn (Blueprint $table) => $table->dropColumn($obsoleteColumn)
            );
        }
    }
};
