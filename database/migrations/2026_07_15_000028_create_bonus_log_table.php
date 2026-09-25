<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: bonus_log
 * Modul: Komisi & Reward
 *
 * Log mutasi debit/credit saldo komisi member.
 * Mencatat setiap penambahan (in) dari perhitungan komisi bulanan,
 * atau pengurangan (out) karena penarikan dana (withdrawal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonus_log', function (Blueprint $table) {
            $table->comment('Log mutasi debit/credit saldo komisi member. Mencatat setiap penambahan (in) dari perhitungan komisi bulanan, atau pengurangan (out) karena penarikan dana (withdrawal).');
            $table->increments('bonus_log_id')->comment('Primary key');
            $table->unsignedInteger('bonus_log_member_id')->comment('ID Member');
            $table->unsignedInteger('bonus_log_value')->default(0)->comment('Nilai nominal mutasi (Rp)');
            $table->enum('bonus_log_type', ['in', 'out'])->default('in')->comment('Tipe mutasi (in=masuk/credit, out=keluar/debit)');
            $table->string('bonus_log_note', 255)->default('')->comment('Keterangan mutasi');
            $table->dateTime('bonus_log_datetime')->comment('Waktu mutasi terjadi');

            $table->index('bonus_log_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_log');
    }
};
