<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: shipping_pickup
 * Modul: Shipping (Polymorphic)
 *
 * Menyimpan data pesanan yang diambil langsung oleh pembeli (self-pickup) di kantor/gudang pusat perusahaan atau outlet stokis.
 * Mendukung relasi polymorphic untuk Transaksi (trx) maupun Retur (return).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_pickup', function (Blueprint $table) {
            $table->comment('Menyimpan data pesanan yang diambil langsung oleh pembeli (self-pickup) (polymorphic untuk trx/return).');
            $table->increments('shipping_pickup_id')->comment('ID Detail Pickup');
            
            // Referensi Polymorphic
            $table->enum('shipping_pickup_ref_type', ['trx', 'return'])->comment('Tipe entitas pengirim (trx=Transaksi, return=Retur)');
            $table->unsignedInteger('shipping_pickup_ref_id')->comment('ID entitas pengirim (trx_id atau return_id)');
            
            $table->enum('shipping_pickup_seller_ref_type', ['warehouse', 'stockist'])->default('stockist')->comment('Tipe penjual asal barang');
            $table->unsignedInteger('shipping_pickup_seller_ref_id')->comment('ID lokasi asal (warehouse_id atau stockist_member_id)');
            $table->longText('shipping_pickup_seller_address')->comment('Alamat pengambilan barang');
            $table->string('shipping_pickup_seller_name', 50)->default('')->comment('Nama outlet/gudang');
            $table->string('shipping_pickup_seller_mobilephone', 16)->default('')->comment('Nomor kontak outlet');
            $table->dateTime('shipping_pickup_schedule_datetime')->nullable()->comment('Rencana tanggal pickup');
            $table->char('shipping_pickup_pin', 5)->nullable()->comment('PIN verifikasi pengambilan (5 digit)');

            $table->index(['shipping_pickup_ref_type', 'shipping_pickup_ref_id'], 'idx_pickup_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_pickup');
    }
};
