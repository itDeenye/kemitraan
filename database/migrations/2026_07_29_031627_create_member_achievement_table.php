<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_achievement', function (Blueprint $table) {
            $table->comment('Rekap pencapaian bulanan member sebagai dasar penentuan kualifikasi upgrade level.');
            $table->increments('member_achievement_id')->comment('ID pencapaian bulanan member');
            $table->unsignedInteger('member_achievement_member_id')->comment('ID member');
            $table->unsignedSmallInteger('member_achievement_year')->comment('Tahun pencapaian (YYYY)');
            $table->unsignedTinyInteger('member_achievement_month')->comment('Bulan pencapaian (1-12)');
            $table->unsignedInteger('member_achievement_point')->default(0)->comment('Total poin pada periode berjalan');
            $table->unsignedInteger('member_achievement_customer_count')->default(0)->comment('Jumlah pelanggan pada periode berjalan');
            $table->unsignedBigInteger('member_achievement_total_trx_amount')->default(0)->comment('Total nilai transaksi pada periode berjalan (Rp)');
            $table->dateTime('member_achievement_create_datetime')->comment('Waktu rekap pencapaian dibuat');

            $table->unique(
                ['member_achievement_member_id', 'member_achievement_year', 'member_achievement_month'],
                'uq_member_achievement_period'
            );
            $table->index(
                ['member_achievement_year', 'member_achievement_month'],
                'idx_member_achievement_period'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_achievement');
    }
};
