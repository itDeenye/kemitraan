<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: member_stock
 * Modul: Stok & Mutasi
 *
 * Stok produk yang saat ini dikuasai oleh member/stokis.
 * Mencatat jumlah sisa stok per produk per member, termasuk stok gantung
 * (hanging stock = pesanan sudah dibayar tapi belum diterima).
 * Struktur simetris dengan `warehouse_stock`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_stock', function (Blueprint $table) {
            $table->comment('Stok produk yang saat ini dikuasai oleh member/stokis. Struktur simetris dengan warehouse_stock, ditambah hanging stock.');
            $table->increments('member_stock_id')->comment('ID stok');
            $table->unsignedInteger('member_stock_member_id')->default(0)->comment('ID member');
            $table->unsignedInteger('member_stock_product_id')->default(0)->comment('ID produk');
            $table->unsignedInteger('member_stock_balance')->default(0)->comment('Sisa saldo stok (Pcs)');
            $table->unsignedInteger('member_stock_transfer_in')->default(0)->comment('Nilai Tertahan Transfer Masuk (Pcs)');
            $table->unsignedInteger('member_stock_transfer_out')->default(0)->comment('Nilai Tertahan Transfer Keluar (Pcs)');

            $table->index('member_stock_member_id');
            $table->index('member_stock_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_stock');
    }
};
