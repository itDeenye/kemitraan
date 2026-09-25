<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: member_stock_log
 * Modul: Stok & Mutasi
 *
 * Log detail mutasi kartu stok produk milik member/stokis.
 * Berpasangan simetris dengan tabel `warehouse_stock_log`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_stock_log', function (Blueprint $table) {
            $table->comment('Log detail mutasi kartu stok produk milik member/stokis. Berpasangan simetris dengan tabel warehouse_stock_log.');
            $table->increments('member_stock_log_id')->comment('ID Log Stok Member');
            $table->unsignedInteger('member_stock_log_member_id')->comment('ID Member');
            $table->unsignedInteger('member_stock_log_product_id')->comment('ID Produk');
            $table->enum('member_stock_log_type', ['in', 'out'])->comment('Jenis Mutasi (in=Masuk, out=Keluar)');
            $table->unsignedInteger('member_stock_log_quantity')->default(0)->comment('Kuantitas Mutasi (Pcs)');
            $table->unsignedInteger('member_stock_log_unit_price')->default(0)->comment('Harga Satuan saat mutasi (bisa harga beli dari upline atau harga jual ke downline, jika retur merujuk ke harga di transaksi asal saat penerimaan)');
            $table->unsignedInteger('member_stock_log_balance')->default(0)->comment('Saldo Akhir Setelah Mutasi (Pcs)');
            $table->string('member_stock_log_note', 255)->comment('Catatan/Keterangan Mutasi');
            $table->dateTime('member_stock_log_datetime')->comment('Waktu Mutasi');

            $table->index('member_stock_log_member_id');
            $table->index('member_stock_log_product_id');
            $table->index('member_stock_log_datetime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_stock_log');
    }
};
