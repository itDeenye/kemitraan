<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('reward_annual_qualified');
        Schema::dropIfExists('reward_annual');

        Schema::table('reward_point_annual', function (Blueprint $table) {
            $table->renameColumn('reward_point_annual_acc', 'reward_point_annual_total_points');
        });

        Schema::table('reward_point_annual', function (Blueprint $table) {
            $table->dropColumn('reward_point_annual_paid');
            $table->index('reward_point_annual_year', 'idx_reward_point_annual_year');
        });

        Schema::table('reward_point_annual_log', function (Blueprint $table) {
            $table->index(
                ['reward_point_annual_log_member_id', 'reward_point_annual_log_datetime'],
                'idx_reward_annual_log_member_datetime'
            );
        });
    }

    public function down(): void
    {
        Schema::table('reward_point_annual_log', function (Blueprint $table) {
            $table->dropIndex('idx_reward_annual_log_member_datetime');
        });

        Schema::table('reward_point_annual', function (Blueprint $table) {
            $table->dropIndex('idx_reward_point_annual_year');
            $table->unsignedInteger('reward_point_annual_paid')
                ->default(0)
                ->after('reward_point_annual_total_points')
                ->comment('Total poin reward tahunan yang sudah dicairkan/diklaim (paid)');
        });

        Schema::table('reward_point_annual', function (Blueprint $table) {
            $table->renameColumn('reward_point_annual_total_points', 'reward_point_annual_acc');
        });

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
};
