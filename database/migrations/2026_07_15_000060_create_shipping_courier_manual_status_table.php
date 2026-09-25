<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: shipping_courier_manual_status
 * Modul: Shipping (Status Log)
 *
 * Log riwayat status pengiriman kurir manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_courier_manual_status', function (Blueprint $table) {
            $table->comment('Log riwayat status pengiriman kurir manual.');
            $table->increments('shipping_courier_manual_status_id')->comment('ID Log Status');
            $table->unsignedInteger('shipping_courier_manual_status_shipping_courier_manual_id')->comment('ID Detail Pengiriman Manual');
            
            // Referensi Polymorphic
            $table->enum('shipping_courier_manual_status_ref_type', ['trx', 'return'])->comment('Tipe entitas pengirim (trx=Transaksi, return=Retur)');
            $table->unsignedInteger('shipping_courier_manual_status_ref_id')->comment('ID entitas pengirim (trx_id atau return_id)');
            
            $table->enum('shipping_courier_manual_status_value', [
                'pending', 'processed_packages', 'shipped_packages', 'cancelled_packages',
                'finished_packages', 'returned_packages', 'completed',
            ])->default('pending')->comment('Nilai status pengiriman');
            $table->string('shipping_courier_manual_status_note', 255)->nullable()->comment('Catatan status (posisi paket)');
            $table->dateTime('shipping_courier_manual_status_datetime')->nullable()->comment('Waktu perubahan status');

            $table->index('shipping_courier_manual_status_shipping_courier_manual_id', 'idx_manual_stat_manual_id');
            $table->index(['shipping_courier_manual_status_ref_type', 'shipping_courier_manual_status_ref_id'], 'idx_manual_stat_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_courier_manual_status');
    }
};
