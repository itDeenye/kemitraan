<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: reward_voucher_monthly
 * Modul: Komisi & Reward (Voucher Bulanan)
 *
 * Menyimpan data saldo voucher belanja bulanan mitra DNY Skincare yang diperoleh dari reward (misal: reward 2.5% stokis).
 * Voucher ini dihitung per bulan/periode, memiliki tanggal kedaluwarsa, dan hangus jika tidak digunakan di bulan berikutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_voucher_monthly', function (Blueprint $table) {
            $table->comment('Menyimpan data saldo voucher belanja bulanan mitra DNY Skincare per periode bulan (didapat dari reward stokis dll).');
            $table->increments('reward_voucher_monthly_id')->comment('ID Voucher Bulanan');
            $table->unsignedInteger('reward_voucher_monthly_member_id')->comment('ID Member');
            $table->unsignedSmallInteger('reward_voucher_monthly_year')->comment('Tahun Berjalan (YYYY)');
            $table->unsignedTinyInteger('reward_voucher_monthly_month')->comment('Bulan Berjalan (1-12)');
            $table->unsignedInteger('reward_voucher_monthly_acc')->default(0)->comment('Total saldo voucher bulanan yang didapat (Rp)');
            $table->unsignedInteger('reward_voucher_monthly_paid')->default(0)->comment('Total saldo voucher bulanan yang sudah dibelanjakan (Rp)');
            $table->unsignedInteger('reward_voucher_monthly_expired')->default(0)->comment('Total saldo voucher bulanan yang hangus/expired (Rp)');
            $table->date('reward_voucher_monthly_expiry_date')->nullable()->comment('Tanggal batas akhir klaim/penggunaan (akhir bulan berikutnya)');
            $table->dateTime('reward_voucher_monthly_last_updated_datetime')->nullable()->comment('Waktu update terakhir');

            $table->unique(['reward_voucher_monthly_member_id', 'reward_voucher_monthly_year', 'reward_voucher_monthly_month'], 'idx_rev_voucher_monthly_uniq');
            $table->index(['reward_voucher_monthly_year', 'reward_voucher_monthly_month'], 'idx_rev_voucher_monthly_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_voucher_monthly');
    }
};
