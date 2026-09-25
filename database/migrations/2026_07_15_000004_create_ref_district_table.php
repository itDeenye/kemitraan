<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: ref_district
 * Modul: Wilayah & Referensi
 *
 * Master data kecamatan seluruh Indonesia.
 * PK menggunakan char(6) sesuai standar kode wilayah BPS.
 * Setiap kecamatan merujuk ke satu kota/kabupaten via `district_city_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_district', function (Blueprint $table) {
            $table->comment('Master data kecamatan seluruh Indonesia. PK menggunakan char(6) sesuai standar kode wilayah BPS. Setiap kecamatan merujuk ke satu kota/kabupaten via `district_city_id`.');
            $table->char('district_id', 6)->primary()->comment('ID Kecamatan (kode BPS 6 digit)');
            $table->char('district_city_id', 4)->comment('ID Kota/Kabupaten');
            $table->string('district_name', 100)->comment('Nama Kecamatan');
            $table->char('district_latitude', 30)->nullable()->comment('Garis lintang');
            $table->char('district_longitude', 30)->nullable()->comment('Garis bujur');

            $table->index('district_city_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_district');
    }
};
