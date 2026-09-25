<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: goods_receive
 * Modul: Stok & Mutasi
 *
 * Header penerimaan barang (goods receive) oleh mitra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receive', function (Blueprint $table) {
            $table->comment('Header penerimaan barang (goods receive) oleh mitra.');
            $table->increments('goods_receive_id')->comment('ID penerimaan barang');
            $table->string('goods_receive_number', 50)->comment('Nomor penerimaan barang');
            $table->unsignedInteger('goods_receive_trx_id')->comment('ID transaksi pembelian pemicu');

            // Penerima (Buyer)
            $table->enum('goods_receive_buyer_type', ['distributor', 'agent', 'reseller'])->comment('Tipe pembeli/penerima stok (distributor, agent, reseller)');
            $table->unsignedInteger('goods_receive_buyer_id')->comment('ID pembeli/penerima (member_id)');

            // Pengirim (Seller)
            $table->enum('goods_receive_seller_type', ['warehouse', 'distributor', 'agent', 'reseller'])->comment('Tipe penjual/pengirim barang (warehouse, distributor, agent, reseller)');
            $table->unsignedInteger('goods_receive_seller_id')->comment('ID penjual/pengirim (warehouse_id atau member_id)');

            // Bukti dokumen penerimaan
            $table->string('goods_receive_delivery_note_number', 50)->nullable()->comment('Nomor surat jalan');
            $table->string('goods_receive_faktur_number', 50)->nullable()->comment('Nomor Faktur');

            // Status
            $table->enum('goods_receive_status', ['transit', 'partial', 'completed', 'returned'])->default('transit')->comment('Status penerimaan (transit=stok menggantung, partial=diterima sebagian, completed=diterima lengkap, returned=diretur)');
            $table->dateTime('goods_receive_created_datetime')->comment('Waktu penerimaan barang');

            $table->unique('goods_receive_number');
            $table->index('goods_receive_trx_id');
            $table->index('goods_receive_buyer_id');
            $table->index('goods_receive_seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receive');
    }
};
