<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: member_address
 * Modul: Kemitraan
 *
 * Alamat pengiriman milik mitra (member).
 * Setiap mitra bisa punya beberapa alamat (rumah, kantor, gudang),
 * dengan salah satu ditandai sebagai default.
 * FK ke ref_province, ref_city, ref_district, ref_subdistrict, ref_country.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_address', function (Blueprint $table) {
            $table->comment('Alamat pengiriman milik mitra (member). Setiap mitra bisa punya beberapa alamat, dengan salah satu ditandai sebagai default.');
            $table->increments('member_address_id')->comment('ID Alamat');
            $table->unsignedInteger('member_address_member_id')->comment('ID Member pemilik alamat');
            $table->string('member_address_label', 50)->default('')->comment('Label alamat (Rumah, Kantor, Gudang)');
            $table->string('member_address_recipient', 150)->default('')->comment('Nama penerima');
            $table->string('member_address_phone', 20)->default('')->comment('Nomor HP penerima');
            $table->string('member_address_full', 500)->default('')->comment('Alamat lengkap (teks bebas)');
            $table->unsignedInteger('member_address_subdistrict_id')->default(0)->comment('ID Kelurahan');
            $table->unsignedInteger('member_address_district_id')->default(0)->comment('ID Kecamatan');
            $table->unsignedInteger('member_address_city_id')->default(0)->comment('ID Kota/Kabupaten');
            $table->unsignedInteger('member_address_province_id')->default(0)->comment('ID Provinsi');
            $table->unsignedInteger('member_address_country_id')->default(0)->comment('ID Negara');
            $table->unsignedTinyInteger('member_address_is_default')->default(0)->comment('Alamat default (1=ya)');

            $table->index('member_address_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_address');
    }
};
