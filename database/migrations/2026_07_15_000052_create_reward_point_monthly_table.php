<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: reward_point_monthly
 * Modul: Reward (Bulanan & Stokis)
 *
 * Menyimpan akumulasi kuantitas produk (pcs) dan total belanja (Rp) bulanan mitra untuk penentuan
 * reward bulanan (Distributor, Agent, Reseller) dan reward Stokis (2.5% jika belanja min 50jt).
 * Akumulasi ini di-reset menjadi nol setiap tanggal 1 bulan berikutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_point_monthly', function (Blueprint $table) {
            $table->comment('Menyimpan akumulasi kuantitas produk (pcs) dan total belanja (Rp) bulanan mitra untuk penentuan reward bulanan (Distributor, Agent, Reseller) dan reward Stokis (2.5% jika belanja min 50jt). Akumulasi ini di-reset menjadi nol setiap tanggal 1 bulan berikutnya.');
            $table->increments('reward_point_monthly_id')->comment('ID Reward Bulanan');
            $table->unsignedInteger('reward_point_monthly_member_id')->comment('ID Member');
            $table->unsignedSmallInteger('reward_point_monthly_year')->comment('Tahun Berjalan (YYYY)');
            $table->unsignedTinyInteger('reward_point_monthly_month')->comment('Bulan Berjalan (1-12)');
            $table->unsignedInteger('reward_point_monthly_total_qty')->default(0)->comment('Total produk yang dibeli dalam 1 bulan (pcs) - untuk reward bulanan level');
            $table->unsignedBigInteger('reward_point_monthly_total_amount')->default(0)->comment('Total nilai belanja dalam 1 bulan (Rp) - untuk reward Stokis');
            
            // Kolom nominal reward yang dicapai
            $table->unsignedInteger('reward_point_monthly_reward_qty_value')->default(0)->comment('Nominal reward kuantitas yang dicapai (Rp)');
            $table->unsignedInteger('reward_point_monthly_reward_stockist_value')->default(0)->comment('Nominal reward Stokis 2.5% yang dicapai (Rp)');
            
            // Status klaim/notifikasi dalam bentuk Voucher
            $table->unsignedTinyInteger('reward_point_monthly_is_processed')->default(0)->comment('Status apakah reward sudah diproses menjadi voucher (1=Ya, 0=Belum)');
            $table->dateTime('reward_point_monthly_processed_datetime')->nullable()->comment('Waktu pemrosesan reward');
            $table->unsignedInteger('reward_point_monthly_reward_voucher_monthly_id')->nullable()->comment('ID Voucher Belanja yang diterbitkan (relasi ke reward_voucher_monthly.reward_voucher_monthly_id)');

            $table->unique(['reward_point_monthly_member_id', 'reward_point_monthly_year', 'reward_point_monthly_month'], 'idx_reward_monthly_uniq');
            $table->index(['reward_point_monthly_year', 'reward_point_monthly_month'], 'idx_reward_monthly_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_point_monthly');
    }
};
