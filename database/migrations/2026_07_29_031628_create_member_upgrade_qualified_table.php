<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_upgrade_qualified', function (Blueprint $table) {
            $table->comment('Daftar member yang memenuhi kualifikasi upgrade level berdasarkan pencapaian bulanan.');
            $table->increments('member_upgrade_qualified_id')->comment('ID kualifikasi upgrade member');
            $table->unsignedInteger('member_upgrade_qualified_member_id')->comment('ID member yang memenuhi kualifikasi');
            $table->unsignedInteger('member_upgrade_qualified_from_level_id')->comment('ID level member sebelum upgrade');
            $table->unsignedInteger('member_upgrade_qualified_to_level_id')->comment('ID level member tujuan');
            $table->unsignedInteger('member_upgrade_qualified_from_year_month')->comment('Periode awal penilaian dalam format YYMM');
            $table->unsignedInteger('member_upgrade_qualified_to_year_month')->comment('Periode akhir penilaian dalam format YYMM');
            $table->enum('member_upgrade_qualified_status', ['requested', 'approved', 'rejected'])
                ->default('requested')
                ->comment('Status proses kualifikasi upgrade');
            $table->unsignedInteger('member_upgrade_qualified_admin_id')->default(0)->comment('ID administrator yang memproses');
            $table->dateTime('member_upgrade_qualified_last_update_datetime')->nullable()->comment('Waktu perubahan terakhir');
            $table->dateTime('member_upgrade_qualified_created_datetime')->comment('Waktu kualifikasi dibuat');

            $table->unique([
                'member_upgrade_qualified_member_id',
                'member_upgrade_qualified_from_level_id',
                'member_upgrade_qualified_to_level_id',
                'member_upgrade_qualified_from_year_month',
                'member_upgrade_qualified_to_year_month',
            ], 'uq_member_upgrade_qualified_period');
            $table->index('member_upgrade_qualified_status', 'idx_member_upgrade_qualified_status');
            $table->index([
                'member_upgrade_qualified_from_year_month',
                'member_upgrade_qualified_to_year_month',
            ], 'idx_member_upgrade_qualified_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_upgrade_qualified');
    }
};
