<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: shipping_courier_express
 * Modul: Shipping (Polymorphic)
 *
 * Menyimpan data pengiriman menggunakan kurir ekspedisi express (integrasi API RajaOngkir/BiteShip/STC, dll).
 * Mendukung relasi polymorphic untuk Transaksi (trx) maupun Retur (return).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_courier_express', function (Blueprint $table) {
            $table->comment('Menyimpan data pengiriman menggunakan kurir ekspedisi express (polymorphic untuk trx/return).');
            $table->increments('shipping_courier_express_id')->comment('ID Detail Pengiriman Express');
            
            // Referensi Polymorphic
            $table->enum('shipping_courier_express_ref_type', ['trx', 'return'])->comment('Tipe entitas pengirim (trx=Transaksi, return=Retur)');
            $table->unsignedInteger('shipping_courier_express_ref_id')->comment('ID entitas pengirim (trx_id atau return_id)');
            
            $table->string('shipping_courier_express_type', 10)->default('')->comment('Tipe ekspedisi');
            $table->string('shipping_courier_express_expedition_name', 20)->default('')->comment('Nama kurir (JNE/J&T/Sicepat)');
            $table->string('shipping_courier_express_expedition_service', 10)->default('')->comment('Layanan kurir (REG/OKE/YES)');
            $table->string('shipping_courier_express_etd', 20)->default('')->comment('Estimasi pengiriman (hari/jam)');
            $table->string('shipping_courier_express_order_id', 50)->default('')->comment('Order ID dari sistem ekspedisi');
            $table->enum('shipping_courier_express_pickup_method', ['DROP-OFF', 'PICKUP'])->default('DROP-OFF')->comment('Metode serah barang');
            $table->string('shipping_courier_express_pickup_number', 50)->default('')->comment('Nomor booking pickup');
            $table->dateTime('shipping_courier_express_schedule_datetime')->nullable()->comment('Jadwal pickup');
            $table->string('shipping_courier_express_awb', 50)->nullable()->comment('Nomor resi pengiriman');
            $table->unsignedInteger('shipping_courier_express_cost')->default(0)->comment('Biaya kirim (Rp)');
            $table->unsignedTinyInteger('shipping_courier_express_insurance_is_force')->default(0)->comment('Wajib asuransi (1=ya)');
            $table->unsignedInteger('shipping_courier_express_insurance')->default(0)->comment('Biaya asuransi (Rp)');
            $table->unsignedInteger('shipping_courier_express_package_weight')->default(0)->comment('Total berat paket (gram)');
            $table->unsignedInteger('shipping_courier_express_package_length')->default(0)->comment('Panjang paket (cm)');
            $table->unsignedInteger('shipping_courier_express_package_width')->default(0)->comment('Lebar paket (cm)');
            $table->unsignedInteger('shipping_courier_express_package_height')->default(0)->comment('Tinggi paket (cm)');

            // === Asal (Origin) ===
            $table->string('shipping_courier_express_origin_name', 50)->default('')->comment('Nama pengirim asal');
            $table->string('shipping_courier_express_origin_phone', 16)->default('')->comment('Telepon pengirim asal');
            $table->text('shipping_courier_express_origin_address')->comment('Alamat asal');
            $table->unsignedInteger('shipping_courier_express_origin_subdistrict_id')->comment('ID kelurahan asal');
            $table->string('shipping_courier_express_origin_subdistrict_name', 50)->default('')->comment('Kelurahan asal');
            $table->string('shipping_courier_express_origin_district_name', 50)->default('')->comment('Kecamatan asal');
            $table->string('shipping_courier_express_origin_city_name', 50)->default('')->comment('Kota asal');
            $table->string('shipping_courier_express_origin_province_name', 50)->default('')->comment('Provinsi asal');
            $table->char('shipping_courier_express_origin_zipcode', 5)->nullable()->comment('Kode pos asal');

            // === Tujuan (Destination) ===
            $table->string('shipping_courier_express_destination_name', 50)->default('')->comment('Nama penerima');
            $table->string('shipping_courier_express_destination_phone', 16)->default('')->comment('Telepon penerima');
            $table->text('shipping_courier_express_destination_address')->comment('Alamat tujuan');
            $table->unsignedInteger('shipping_courier_express_destination_subdistrict_id')->comment('ID kelurahan tujuan');
            $table->string('shipping_courier_express_destination_subdistrict_name', 50)->default('')->comment('Kelurahan tujuan');
            $table->string('shipping_courier_express_destination_district_name', 50)->default('')->comment('Kecamatan tujuan');
            $table->string('shipping_courier_express_destination_city_name', 50)->default('')->comment('Kota tujuan');
            $table->string('shipping_courier_express_destination_province_name', 50)->default('')->comment('Provinsi tujuan');
            $table->char('shipping_courier_express_destination_zipcode', 5)->nullable()->comment('Kode pos tujuan');

            $table->index(['shipping_courier_express_ref_type', 'shipping_courier_express_ref_id'], 'idx_express_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_courier_express');
    }
};
