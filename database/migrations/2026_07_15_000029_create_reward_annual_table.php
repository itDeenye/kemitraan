<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: reward_annual
 * Modul: Komisi & Reward (Tahunan)
 *
 * Master data reward tahunan (motor, mobil, dll) yang bisa diraih member.
 * Menyimpan syarat-syarat kualifikasi (poin tahunan, total pembelanjaan pcs,
 * pasien aktif, atau rekrut reseller) dan nilai nominal reward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_annual', function (Blueprint $table) {
            $table->comment('Master data reward tahunan (motor, mobil, dll) yang bisa diraih member.');
            $table->increments('reward_annual_id')->comment('ID unik reward tahunan');
            $table->string('reward_annual_title', 100)->default('')->comment('Nama reward tahunan');
            $table->text('reward_annual_description')->nullable()->comment('Deskripsi lengkap reward tahunan');
            $table->string('reward_annual_image', 255)->default('')->comment('Path gambar reward');
            $table->string('reward_annual_image_filename', 255)->default('')->comment('Nama file gambar reward');
            $table->unsignedInteger('reward_annual_value')->default(0)->comment('Nilai nominal reward tahunan (Rp)');
            $table->unsignedInteger('reward_annual_condition_points')->default(0)->comment('Syarat akumulasi poin reward tahunan');
            $table->unsignedInteger('reward_annual_condition_sales_pcs')->default(0)->comment('Syarat akumulasi pembelanjaan (pcs)');
            $table->unsignedInteger('reward_annual_condition_active_patients')->default(0)->comment('Syarat minimal pasien aktif');
            $table->unsignedInteger('reward_annual_condition_recruited_resellers')->default(0)->comment('Syarat minimal rekrut reseller baru');
            $table->unsignedTinyInteger('reward_annual_is_active')->default(1)->comment('Status aktif reward (1=aktif, 0=nonaktif)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_annual');
    }
};
