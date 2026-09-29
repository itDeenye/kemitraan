<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: bank_company
 * Modul: Perusahaan
 *
 * Daftar rekening bank resmi milik perusahaan DNY (PT. Deenye Berkah Abadi).
 * Digunakan untuk menerima transfer pembayaran dari member
 * dan ditampilkan di halaman konfirmasi pembayaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_company', function (Blueprint $table) {
            $table->comment('Daftar rekening bank resmi milik perusahaan DNY. Digunakan untuk menerima transfer pembayaran dari member dan ditampilkan di halaman konfirmasi pembayaran.');
            $table->increments('bank_company_id')->comment('ID Rekening Bank Perusahaan');
            $table->unsignedInteger('bank_company_bank_id')->comment('ID Bank');
            $table->string('bank_company_bank_acc_name', 50)->default('')->comment('Nama Rekening');
            $table->string('bank_company_bank_acc_number', 50)->default('')->comment('Nomor Rekening');
            $table->unsignedTinyInteger('bank_company_bank_is_active')->default(1)->comment('Status Aktif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_company');
    }
};
