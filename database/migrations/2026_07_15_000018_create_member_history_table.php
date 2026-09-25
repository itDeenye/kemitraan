<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: member_history
 * Modul: Kemitraan
 *
 * Log perubahan level atau status keaktifan member.
 * Mencatat setiap event upgrade, downgrade, aktivasi, atau suspensi
 * beserta siapa admin yang menyetujui dan alasannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_history', function (Blueprint $table) {
            $table->comment('Log perubahan level atau status keaktifan member. Mencatat setiap event upgrade, downgrade, aktivasi, atau suspensi beserta siapa admin yang menyetujui dan alasannya.');
            $table->increments('member_history_id')->comment('ID History');
            $table->unsignedInteger('member_history_member_id')->comment('ID Member');
            $table->string('member_history_action', 30)->default('')->comment('Jenis aksi (upgrade|downgrade|activate|suspend)');
            $table->enum('member_history_from_level', ['reseller', 'agent', 'distributor'])->nullable()->comment('Level kemitraan asal');
            $table->enum('member_history_to_level', ['reseller', 'agent', 'distributor'])->nullable()->comment('Level kemitraan tujuan');
            $table->unsignedInteger('member_history_upline_member_id')->default(0)->comment('ID Member Upline saat history dicatat');
            $table->enum('member_history_upline_level', ['reseller', 'agent', 'distributor'])->nullable()->comment('Level upline saat history dicatat');
            $table->unsignedInteger('member_history_downline_member_id')->default(0)->comment('ID Member Downline saat history dicatat');
            $table->string('member_history_reason', 500)->default('')->comment('Alasan perubahan');
            $table->unsignedInteger('member_history_approved_by')->default(0)->comment('ID Administrator yang approve');
            $table->dateTime('member_history_datetime')->comment('Waktu perubahan');

            $table->index('member_history_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_history');
    }
};
