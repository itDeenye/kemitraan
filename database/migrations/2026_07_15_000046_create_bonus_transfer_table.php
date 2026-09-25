<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: bonus_transfer
 * Modul: Komisi & Reward
 *
 * Mencatat pengiriman uang bonus dari kas perusahaan ke rekening member.
 * Menyimpan detail info bank tujuan dan status approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonus_transfer', function (Blueprint $table) {
            $table->comment('Mencatat pengiriman uang bonus dari kas perusahaan ke rekening member. Menyimpan detail info bank tujuan dan status approval.');
            $table->increments('bonus_transfer_id')->comment('ID Transfer Bonus');
            $table->string('bonus_transfer_code', 50)->default('')->comment('Kode Transfer');
            $table->unsignedInteger('bonus_transfer_member_id')->comment('ID Mitra');
            $table->unsignedInteger('bonus_transfer_value')->default(0)->comment('Nilai total bonus (Rp)');
            $table->unsignedInteger('bonus_transfer_nett')->default(0)->comment('Nilai nett setelah potongan (Rp)');
            $table->unsignedInteger('bonus_transfer_member_bank_id')->comment('ID Bank Mitra');
            $table->string('bonus_transfer_member_bank_name', 100)->comment('Nama Bank Mitra');
            $table->string('bonus_transfer_member_bank_account_name', 50)->comment('Nama Rekening Mitra');
            $table->string('bonus_transfer_member_bank_account_no', 50)->comment('Nomor Rekening Mitra');
            $table->unsignedInteger('bonus_transfer_status_administrator_id')->nullable()->comment('ID Admin Approval');
            $table->enum('bonus_transfer_status', ['pending', 'processing', 'success', 'failed', 'completed'])->nullable()->comment('Status transfer');
            $table->dateTime('bonus_transfer_status_datetime')->nullable()->comment('Waktu perubahan status');
            $table->date('bonus_transfer_date')->comment('Tanggal Transfer');
            $table->dateTime('bonus_transfer_datetime')->comment('Waktu Transfer');

            $table->index('bonus_transfer_code');
            $table->index('bonus_transfer_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_transfer');
    }
};
