<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: shipping_pickup_status
 * Modul: Shipping (Status Log)
 *
 * Log riwayat status pengambilan barang (self-pickup).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_pickup_status', function (Blueprint $table) {
            $table->comment('Log riwayat status pengambilan barang (self-pickup).');
            $table->increments('shipping_pickup_status_id')->comment('ID Log Status');
            $table->unsignedInteger('shipping_pickup_status_shipping_pickup_id')->comment('ID Detail Pickup');
            
            // Referensi Polymorphic
            $table->enum('shipping_pickup_status_ref_type', ['trx', 'return'])->comment('Tipe entitas pengirim (trx=Transaksi, return=Retur)');
            $table->unsignedInteger('shipping_pickup_status_ref_id')->comment('ID entitas pengirim (trx_id atau return_id)');
            
            $table->enum('shipping_pickup_status_value', ['pending', 'ready_to_pickup', 'picked_up', 'completed'])->default('pending')->comment('Nilai status pickup');
            $table->dateTime('shipping_pickup_status_datetime')->nullable()->comment('Waktu perubahan status');

            $table->index('shipping_pickup_status_shipping_pickup_id', 'idx_pickup_stat_pickup_id');
            $table->index(['shipping_pickup_status_ref_type', 'shipping_pickup_status_ref_id'], 'idx_pickup_stat_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_pickup_status');
    }
};
