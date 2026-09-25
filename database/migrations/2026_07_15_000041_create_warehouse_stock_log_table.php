<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: warehouse_stock_log
 * Modul: Stok & Mutasi (Pusat)
 *
 * Log detail mutasi kartu stok produk milik pusat/gudang.
 * Berpasangan simetris dengan tabel `member_stock_log` milik mitra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_stock_log', function (Blueprint $table) {
            $table->comment('Log detail mutasi kartu stok produk milik pusat/gudang. Berpasangan simetris dengan tabel member_stock_log milik mitra.');
            $table->increments('warehouse_stock_log_id')->comment('ID Log Stok Gudang');
            $table->unsignedInteger('warehouse_stock_log_warehouse_id')->default(0)->comment('ID Gudang');
            $table->unsignedInteger('warehouse_stock_log_product_id')->default(0)->comment('ID Produk');
            $table->enum('warehouse_stock_log_type', ['in', 'out'])->comment('Jenis Mutasi (in=Masuk, out=Keluar)');
            $table->unsignedInteger('warehouse_stock_log_quantity')->default(0)->comment('Kuantitas Mutasi (Pcs)');
            $table->unsignedInteger('warehouse_stock_log_unit_price')->default(0)->comment('Harga Satuan / HPP saat mutasi (Rp)');
            $table->unsignedInteger('warehouse_stock_log_balance')->default(0)->comment('Saldo Akhir Setelah Mutasi (Pcs)');
            $table->string('warehouse_stock_log_note', 255)->comment('Catatan/Keterangan Mutasi');
            $table->dateTime('warehouse_stock_log_datetime')->nullable()->comment('Waktu Mutasi');

            $table->index('warehouse_stock_log_warehouse_id', 'idx_wh_log_wh_id');
            $table->index('warehouse_stock_log_product_id', 'idx_wh_log_prod_id');
            $table->index('warehouse_stock_log_datetime', 'idx_wh_log_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stock_log');
    }
};
