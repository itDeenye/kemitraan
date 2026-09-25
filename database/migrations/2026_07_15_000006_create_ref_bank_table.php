<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: ref_bank
 * Modul: Wilayah & Referensi
 *
 * Master data bank di Indonesia (BCA, Mandiri, BRI, BNI, dll).
 * Menyimpan kode bank, nama, logo, konfigurasi disbursement & VA,
 * dan status keaktifan. Digunakan sebagai referensi di `member_bank_account`,
 * `bank_company`, dan pembayaran via gateway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_bank', function (Blueprint $table) {
            $table->comment('Master data bank di Indonesia (BCA, Mandiri, BRI, BNI, dll). Menyimpan kode bank, nama, logo, konfigurasi disbursement & VA, dan status keaktifan. Digunakan sebagai referensi di `member_bank_account`, `bank_company`, dan pembayaran via gateway.');
            $table->increments('bank_id')->comment('ID Bank');
            $table->string('bank_code', 50)->comment('Kode Bank');
            $table->string('bank_name', 255)->comment('Nama Bank');
            $table->string('bank_logo', 255)->default('')->comment('Logo Bank');
            $table->unsignedTinyInteger('bank_is_active')->default(1)->comment('Status Aktif');
            $table->dateTime('bank_inserted_datetime')->nullable()->comment('Tanggal input');
            $table->dateTime('bank_updated_datetime')->nullable()->comment('Tanggal update');
            $table->dateTime('bank_deleted_datetime')->nullable()->comment('Tanggal dihapus (soft delete)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_bank');
    }
};
