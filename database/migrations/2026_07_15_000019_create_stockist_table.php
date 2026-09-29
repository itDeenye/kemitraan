<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: stockist
 * Modul: Kemitraan
 *
 * Data registrasi dan profil stokis (titik distribusi).
 * PK = member_id (relasi 1:1 dengan `member`).
 * Stokis adalah member yang disetujui untuk menjadi titik distribusi
 * produk di wilayahnya, memiliki lokasi koordinat dan e-wallet sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stockist', function (Blueprint $table) {
            $table->comment('Data registrasi dan profil stokis (titik distribusi). Stokis adalah member yang disetujui untuk menjadi titik distribusi produk di wilayahnya, memiliki lokasi koordinat dan e-wallet sendiri.');
            $table->increments('stockist_id')->comment('ID Stokis');
            $table->unsignedInteger('stockist_member_id')->comment('ID Member');
            $table->enum('stockist_type', ['mobile', 'master'])->comment('Tipe stockist');
            $table->string('stockist_name', 100)->default('')->comment('Nama stockist');
            $table->string('stockist_email', 100)->default('')->comment('Email stockist');
            $table->string('stockist_address', 255)->default('')->comment('Alamat stockist');
            $table->string('stockist_mobilephone', 15)->default('')->comment('Nomor kontak stockist');
            $table->string('stockist_image', 255)->default('')->comment('Gambar profil stockist');
            $table->unsignedInteger('stockist_subdistrict_id')->default(0)->comment('ID kelurahan stockist');
            $table->unsignedInteger('stockist_district_id')->default(0)->comment('ID kecamatan stockist');
            $table->unsignedInteger('stockist_city_id')->default(0)->comment('ID kota/kabupaten stockist');
            $table->unsignedInteger('stockist_province_id')->default(0)->comment('ID provinsi stockist');
            $table->string('stockist_latitude', 50)->default('0')->comment('Garis lintang');
            $table->string('stockist_longitude', 50)->default('0')->comment('Garis bujur');
            $table->text('stockist_note')->comment('Catatan stockist (jam operasional, dsb)');
            $table->integer('stockist_ewallet_balance')->default(0)->comment('Saldo dana dari klaim penjualan');
            $table->unsignedTinyInteger('stockist_is_active')->default(1)->comment('Status aktif stockist');
            $table->unsignedTinyInteger('stockist_is_deleted')->default(0)->comment('Soft delete flag');
            $table->dateTime('stockist_input_datetime')->comment('Tanggal input');

            $table->unique('stockist_member_id');
            $table->index('stockist_city_id');
            $table->index('stockist_province_id');
            $table->index('stockist_subdistrict_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stockist');
    }
};
