<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: warehouse_stock
 * Modul: Stok & Mutasi (Pusat)
 *
 * Menyimpan sisa saldo real-time stok produk milik pusat (gudang) per warehouse.
 * Berpasangan simetris dengan tabel `member_stock` milik mitra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_stock', function (Blueprint $table) {
            $table->comment('Menyimpan sisa saldo real-time stok produk milik pusat (gudang). Berpasangan simetris dengan tabel member_stock milik mitra.');
            $table->increments('warehouse_stock_id')->comment('ID Stok Gudang');
            $table->unsignedInteger('warehouse_stock_warehouse_id')->default(0)->comment('ID Gudang');
            $table->unsignedInteger('warehouse_stock_product_id')->default(0)->comment('ID Produk');
            $table->unsignedInteger('warehouse_stock_balance')->default(0)->comment('Sisa Saldo Stok (Pcs)');
            $table->unsignedInteger('warehouse_stock_transfer_in')->default(0)->comment('Nilai Tertahan Transfer Masuk (Pcs)');
            $table->unsignedInteger('warehouse_stock_transfer_out')->default(0)->comment('Nilai Tertahan Transfer Keluar (Pcs)');

            $table->index('warehouse_stock_warehouse_id', 'idx_wh_stock_wh_id');
            $table->index('warehouse_stock_product_id', 'idx_wh_stock_prod_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stock');
    }
};
