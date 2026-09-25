<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: reward_voucher_monthly_log
 * Modul: Komisi & Reward (Voucher Bulanan)
 *
 * Log detail mutasi penambahan, pemakaian, dan hangus (expired) saldo voucher belanja bulanan member.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_voucher_monthly_log', function (Blueprint $table) {
            $table->comment('Log detail mutasi penambahan, pemakaian, dan hangus (expired) saldo voucher belanja bulanan member.');
            $table->increments('reward_voucher_monthly_log_id')->comment('ID Log Voucher Bulanan');
            $table->unsignedInteger('reward_voucher_monthly_log_member_id')->comment('ID Member');
            
            // Referensi ke Master Saldo Voucher
            $table->unsignedInteger('reward_voucher_monthly_log_parent_id')->comment('ID referensi ke reward_voucher_monthly_id');
            
            $table->enum('reward_voucher_monthly_log_type', ['in', 'out', 'expire'])->comment('Jenis mutasi (in=didapat, out=dibelanjakan, expire=hangus)');
            $table->unsignedInteger('reward_voucher_monthly_log_amount')->default(0)->comment('Nilai mutasi voucher (Rp)');
            $table->unsignedInteger('reward_voucher_monthly_log_trx_id')->nullable()->comment('ID Transaksi Pemicu (jika type=out)');
            $table->string('reward_voucher_monthly_log_note', 255)->default('')->comment('Catatan/Keterangan Mutasi');
            $table->dateTime('reward_voucher_monthly_log_datetime')->comment('Waktu mutasi');

            $table->index('reward_voucher_monthly_log_member_id', 'idx_rev_voucher_log_member');
            $table->index('reward_voucher_monthly_log_parent_id', 'idx_rev_voucher_log_parent');
            $table->index('reward_voucher_monthly_log_trx_id', 'idx_rev_voucher_log_trx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_voucher_monthly_log');
    }
};
