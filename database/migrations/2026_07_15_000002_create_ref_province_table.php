<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: ref_province
 * Modul: Wilayah & Referensi
 *
 * Master data provinsi seluruh Indonesia.
 * PK menggunakan char(2) sesuai standar kode wilayah Indonesia (BPS).
 * Menjadi parent dari `ref_city` dan referensi di `member` & `member_address`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_province', function (Blueprint $table) {
            $table->comment('Master data provinsi seluruh Indonesia. PK menggunakan char(2) sesuai standar kode wilayah Indonesia (BPS). Menjadi parent dari `ref_city` dan referensi di `member` & `member_address`.');
            $table->char('province_id', 2)->primary()->comment('ID Provinsi (kode BPS 2 digit)');
            $table->string('province_name', 100)->comment('Nama Provinsi');
            $table->char('province_latitude', 30)->nullable()->comment('Garis lintang');
            $table->char('province_longitude', 30)->nullable()->comment('Garis bujur');
            $table->enum('province_is_active', ['0', '1'])->default('1')->comment('Status aktif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_province');
    }
};
