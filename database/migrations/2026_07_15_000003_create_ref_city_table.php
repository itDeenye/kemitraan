<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: ref_city
 * Modul: Wilayah & Referensi
 *
 * Master data kabupaten/kota seluruh Indonesia.
 * PK menggunakan char(4) sesuai standar kode wilayah BPS.
 * Setiap kota merujuk ke satu provinsi via `city_province_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_city', function (Blueprint $table) {
            $table->comment('Master data kabupaten/kota seluruh Indonesia. PK menggunakan char(4) sesuai standar kode wilayah BPS. Setiap kota merujuk ke satu provinsi via `city_province_id`.');
            $table->char('city_id', 4)->primary()->comment('ID Kota (kode BPS 4 digit)');
            $table->char('city_province_id', 2)->comment('ID Provinsi');
            $table->string('city_name', 100)->comment('Nama Kota/Kabupaten');
            $table->string('city_type', 50)->comment('Tipe Kota (Kabupaten|Kota)');
            $table->char('city_latitude', 30)->nullable()->comment('Garis Lintang');
            $table->char('city_longitude', 30)->nullable()->comment('Garis Bujur');
            $table->unsignedTinyInteger('city_is_active')->default(1)->comment('Status Aktif');

            $table->index('city_province_id');
            $table->index('city_is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_city');
    }
};
