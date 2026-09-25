<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: warehouse
 * Modul: Gudang / Perusahaan
 *
 * Profil data legalitas perusahaan/gudang DNY (PT. Deenye Berkah Abadi).
 * Menyimpan informasi resmi yang ditampilkan di invoice, footer website,
 * dan dokumen legal. Diacu oleh `bank_company` dan `warehouse_stock`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse', function (Blueprint $table) {
            $table->comment('Profil data legalitas perusahaan/gudang DNY. Menyimpan informasi resmi yang ditampilkan di invoice, footer website, dan dokumen legal.');
            $table->increments('warehouse_id')->comment('ID Gudang/Perusahaan');
            $table->string('warehouse_name', 200)->default('')->comment('Nama brand/merek perusahaan');
            $table->string('warehouse_legal_name', 200)->default('')->comment('Nama badan hukum (PT. xxx)');
            $table->string('warehouse_npwp', 50)->default('')->comment('Nomor Pokok Wajib Pajak');
            $table->string('warehouse_phone', 30)->default('')->comment('Nomor telepon kantor');
            $table->string('warehouse_email', 150)->default('')->comment('Email resmi perusahaan');
            $table->string('warehouse_logo', 255)->default('')->comment('Path logo perusahaan');
            $table->string('warehouse_address', 255)->default('')->comment('Alamat kantor/gudang pusat');
            $table->unsignedInteger('warehouse_province_id')->default(0)->comment('ID Provinsi');
            $table->unsignedInteger('warehouse_city_id')->default(0)->comment('ID Kota/Kabupaten');
            $table->unsignedInteger('warehouse_district_id')->default(0)->comment('ID Kecamatan');
            $table->unsignedInteger('warehouse_subdistrict_id')->default(0)->comment('ID Kelurahan');
            $table->string('warehouse_latitude', 255)->nullable()->comment('Latitude Koordinat');
            $table->string('warehouse_longitude', 255)->nullable()->comment('Longitude Koordinat');
            $table->unsignedTinyInteger('warehouse_is_active')->default(1)->comment('Status keaktifan (1=aktif, 0=nonaktif)');
            $table->dateTime('warehouse_created_datetime')->nullable()->comment('Tanggal input');

            $table->index('warehouse_province_id');
            $table->index('warehouse_city_id');
            $table->index('warehouse_district_id');
            $table->index('warehouse_subdistrict_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse');
    }
};
