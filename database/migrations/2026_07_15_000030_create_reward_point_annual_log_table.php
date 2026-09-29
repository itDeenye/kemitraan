<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: reward_point_annual_log
 * Modul: Reward (Tahunan)
 *
 * Log riwayat mutasi / kontribusi per transaksi terhadap akumulasi poin reward tahunan member.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_point_annual_log', function (Blueprint $table) {
            $table->comment('Log riwayat mutasi / kontribusi per transaksi terhadap akumulasi poin reward tahunan member.');
            $table->increments('reward_point_annual_log_id')->comment('ID Log Reward Tahunan');
            $table->unsignedInteger('reward_point_annual_log_member_id')->comment('ID Member');
            $table->unsignedInteger('reward_point_annual_log_trx_id')->nullable()->comment('ID Transaksi Pemicu');
            $table->enum('reward_point_annual_log_type', ['in', 'out'])->comment('Jenis Mutasi (in=Masuk, out=Keluar)');
            $table->unsignedInteger('reward_point_annual_log_points')->default(0)->comment('Jumlah Poin Mutasi');
            $table->string('reward_point_annual_log_note', 255)->default('')->comment('Catatan pemicu mutasi');
            $table->dateTime('reward_point_annual_log_datetime')->comment('Waktu pencatatan log');

            $table->index('reward_point_annual_log_member_id', 'idx_reward_annual_log_member');
            $table->index('reward_point_annual_log_trx_id', 'idx_reward_annual_log_trx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_point_annual_log');
    }
};
