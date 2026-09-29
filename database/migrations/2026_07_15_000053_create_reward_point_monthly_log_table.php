<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: reward_point_monthly_log
 * Modul: Reward (Bulanan & Stokis)
 *
 * Log riwayat mutasi / kontribusi per transaksi terhadap akumulasi reward bulanan member/stokis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_point_monthly_log', function (Blueprint $table) {
            $table->comment('Log riwayat mutasi / kontribusi per transaksi terhadap akumulasi reward bulanan member/stokis.');
            $table->increments('reward_point_monthly_log_id')->comment('ID Log Reward Bulanan');
            $table->unsignedInteger('reward_point_monthly_log_member_id')->comment('ID Member');
            $table->unsignedInteger('reward_point_monthly_log_trx_id')->comment('ID Transaksi Pemicu');
            $table->enum('reward_point_monthly_log_type', ['in', 'out'])->default('in')->comment('Jenis Mutasi (in=Masuk, out=Keluar)');
            $table->unsignedInteger('reward_point_monthly_log_qty')->default(0)->comment('Kuantitas produk mutasi (pcs)');
            $table->unsignedBigInteger('reward_point_monthly_log_amount')->default(0)->comment('Nilai belanja mutasi (Rp)');
            $table->string('reward_point_monthly_log_note', 255)->default('')->comment('Catatan pemicu mutasi');
            $table->dateTime('reward_point_monthly_log_datetime')->comment('Waktu pencatatan log');

            $table->index('reward_point_monthly_log_member_id', 'idx_reward_monthly_log_member');
            $table->index('reward_point_monthly_log_trx_id', 'idx_reward_monthly_log_trx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_point_monthly_log');
    }
};
