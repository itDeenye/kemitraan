<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: ref_country
 * Modul: Wilayah & Referensi
 *
 * Master data negara. Digunakan sebagai referensi FK di tabel `member`
 * dan `member_address` untuk identifikasi kewarganegaraan atau negara tujuan pengiriman.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_country', function (Blueprint $table) {
            $table->comment('Master data negara. Digunakan sebagai referensi FK di tabel `member` dan `member_address` untuk identifikasi kewarganegaraan atau negara tujuan pengiriman.');
            $table->increments('country_id')->comment('ID unik negara');
            $table->string('country_iso_code', 5)->nullable()->comment('Kode ISO negara (ID, MY, SG)');
            $table->string('country_phone_code', 25)->nullable()->comment('Kode telepon negara (+62, +60)');
            $table->string('country_name', 100)->nullable()->comment('Nama lengkap negara');
            $table->string('country_flag', 100)->nullable()->comment('Path icon bendera negara');
            $table->enum('country_is_active', ['0', '1'])->default('1')->comment('Status aktif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_country');
    }
};
