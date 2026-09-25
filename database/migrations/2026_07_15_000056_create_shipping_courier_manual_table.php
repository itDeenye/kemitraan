<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: shipping_courier_manual
 * Modul: Shipping (Polymorphic)
 *
 * Menyimpan data pengiriman menggunakan kurir manual/kustom (misal: armada pengiriman perusahaan sendiri).
 * Mendukung relasi polymorphic untuk Transaksi (trx) maupun Retur (return).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_courier_manual', function (Blueprint $table) {
            $table->comment('Menyimpan data pengiriman menggunakan kurir manual (polymorphic untuk trx/return).');
            $table->increments('shipping_courier_manual_id')->comment('ID Detail Pengiriman Manual');
            
            // Referensi Polymorphic
            $table->enum('shipping_courier_manual_ref_type', ['trx', 'return'])->comment('Tipe entitas pengirim (trx=Transaksi, return=Retur)');
            $table->unsignedInteger('shipping_courier_manual_ref_id')->comment('ID entitas pengirim (trx_id atau return_id)');
            
            $table->string('shipping_courier_manual_name', 50)->default('')->comment('Nama kurir/ekspedisi');
            $table->string('shipping_courier_manual_service', 50)->default('')->comment('Layanan pengiriman');
            $table->string('shipping_courier_manual_awb', 50)->nullable()->comment('Nomor resi manual');
            $table->unsignedInteger('shipping_courier_manual_price')->default(0)->comment('Biaya kirim (Rp)');
            $table->unsignedInteger('shipping_courier_manual_package_weight')->default(0)->comment('Total berat paket (gram)');
            $table->string('shipping_courier_manual_package_dimension', 50)->default('')->comment('Dimensi paket');

            // === Asal (Origin) ===
            $table->string('shipping_courier_manual_origin_name', 50)->default('')->comment('Nama pengirim asal');
            $table->string('shipping_courier_manual_origin_phone', 16)->default('')->comment('Telepon pengirim asal');
            $table->text('shipping_courier_manual_origin_address')->comment('Alamat asal');
            $table->unsignedInteger('shipping_courier_manual_origin_subdistrict_id')->comment('ID kelurahan asal');
            $table->string('shipping_courier_manual_origin_subdistrict_name', 50)->default('')->comment('Kelurahan asal');
            $table->string('shipping_courier_manual_origin_district_name', 50)->default('')->comment('Kecamatan asal');
            $table->string('shipping_courier_manual_origin_city_name', 50)->default('')->comment('Kota asal');
            $table->string('shipping_courier_manual_origin_province_name', 50)->default('')->comment('Provinsi asal');
            $table->char('shipping_courier_manual_origin_zipcode', 5)->nullable()->comment('Kode pos asal');

            // === Tujuan (Destination) ===
            $table->string('shipping_courier_manual_destination_name', 50)->default('')->comment('Nama penerima');
            $table->string('shipping_courier_manual_destination_phone', 16)->default('')->comment('Telepon penerima');
            $table->text('shipping_courier_manual_destination_address')->comment('Alamat tujuan');
            $table->unsignedInteger('shipping_courier_manual_destination_subdistrict_id')->comment('ID kelurahan tujuan');
            $table->string('shipping_courier_manual_destination_subdistrict_name', 50)->default('')->comment('Kelurahan tujuan');
            $table->string('shipping_courier_manual_destination_district_name', 50)->default('')->comment('Kecamatan tujuan');
            $table->string('shipping_courier_manual_destination_city_name', 50)->default('')->comment('Kota tujuan');
            $table->string('shipping_courier_manual_destination_province_name', 50)->default('')->comment('Provinsi tujuan');
            $table->char('shipping_courier_manual_destination_zipcode', 5)->nullable()->comment('Kode pos tujuan');

            $table->index(['shipping_courier_manual_ref_type', 'shipping_courier_manual_ref_id'], 'idx_manual_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_courier_manual');
    }
};
