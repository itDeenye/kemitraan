<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: ref_subdistrict
 * Modul: Wilayah & Referensi
 *
 * Master data kelurahan/desa beserta kode pos seluruh Indonesia.
 * PK menggunakan int (bukan auto-increment, di-set manual dari data BPS).
 * Setiap kelurahan merujuk ke satu kecamatan via `subdistrict_district_id`.
 * Level terkecil pada hierarki wilayah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_subdistrict', function (Blueprint $table) {
            $table->comment('Master data kelurahan/desa beserta kode pos seluruh Indonesia. PK menggunakan int (bukan auto-increment, di-set manual dari data BPS). Setiap kelurahan merujuk ke satu kecamatan via `subdistrict_district_id`. Level terkecil pada hierarki wilayah.');
            $table->integer('subdistrict_id')->default(0)->primary()->comment('ID Kelurahan/Desa');
            $table->integer('subdistrict_district_id')->nullable()->comment('ID Kecamatan');
            $table->string('subdistrict_name', 100)->nullable()->comment('Nama Kelurahan/Desa');
            $table->integer('subdistrict_zip_code')->nullable()->comment('Kode Pos');

            $table->index('subdistrict_district_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_subdistrict');
    }
};
