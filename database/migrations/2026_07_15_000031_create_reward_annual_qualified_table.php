<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: reward_annual_qualified
 * Modul: Komisi & Reward (Tahunan)
 *
 * Log pencapaian member yang berhasil lolos kualifikasi mendapatkan reward tahunan.
 * Mencatat syarat yang dipenuhi (poin/pcs/pasien), status approval management,
 * dan status serah terima/klaim.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_annual_qualified', function (Blueprint $table) {
            $table->comment('Log pencapaian member yang berhasil lolos kualifikasi mendapatkan reward tahunan.');
            $table->increments('reward_annual_qualified_id')->comment('ID Qualified');
            $table->unsignedInteger('reward_annual_qualified_member_id')->comment('ID Member yang qualified');
            $table->unsignedInteger('reward_annual_qualified_reward_annual_id')->comment('ID Reward Tahunan');
            $table->unsignedSmallInteger('reward_annual_qualified_year')->comment('Tahun qualified reward');
            $table->string('reward_annual_qualified_reward_title', 100)->nullable()->comment('Nama Reward');
            $table->unsignedInteger('reward_annual_qualified_reward_value')->default(0)->comment('Nilai Reward (Rp)');
            $table->unsignedInteger('reward_annual_qualified_condition_points')->default(0)->comment('Syarat akumulasi poin reward');
            $table->unsignedInteger('reward_annual_qualified_condition_sales_pcs')->default(0)->comment('Syarat akumulasi pembelanjaan (pcs)');
            $table->unsignedInteger('reward_annual_qualified_condition_active_patients')->default(0)->comment('Syarat pasien aktif (jika ada)');
            $table->dateTime('reward_annual_qualified_datetime')->comment('Waktu kualifikasi tercapai');
            $table->enum('reward_annual_qualified_status', ['pending', 'approved', 'rejected'])->default('pending')->comment('Status approval management');
            $table->unsignedInteger('reward_annual_qualified_status_administrator_id')->default(0)->comment('ID Admin yang menyetujui');
            $table->dateTime('reward_annual_qualified_status_datetime')->nullable()->comment('Tanggal approval/reject');
            $table->enum('reward_annual_qualified_claim', ['claimed', 'unclaimed'])->default('unclaimed')->comment('Status serah terima reward');
            $table->dateTime('reward_annual_qualified_claim_datetime')->nullable()->comment('Tanggal serah terima');

            $table->index('reward_annual_qualified_member_id', 'idx_reward_ann_qual_member');
            $table->index('reward_annual_qualified_reward_annual_id', 'idx_reward_ann_qual_reward');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_annual_qualified');
    }
};
