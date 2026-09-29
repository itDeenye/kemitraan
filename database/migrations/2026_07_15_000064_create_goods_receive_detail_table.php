<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: goods_receive_detail
 * Modul: Stok & Mutasi
 *
 * Detail penerimaan barang, pencatatan batch produk, tanggal kedaluwarsa,
 * dan sisa saldo stok berjalan milik member.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receive_detail', function (Blueprint $table) {
            $table->comment('Detail penerimaan barang, pencatatan batch produk, tanggal kedaluwarsa, dan sisa saldo stok berjalan milik member.');
            $table->increments('goods_receive_detail_id')->comment('ID detail penerimaan barang');
            $table->unsignedInteger('goods_receive_detail_receive_id')->comment('ID penerimaan barang (header)');
            $table->unsignedInteger('goods_receive_detail_product_id')->comment('ID Produk');
            $table->string('goods_receive_detail_batch_number', 50)->comment('Nomor Batch');
            $table->date('goods_receive_detail_expire_date')->comment('Tanggal Kedaluwarsa');
            
            $table->unsignedInteger('goods_receive_detail_qty')->default(0)->comment('Kuantitas barang yang diterima dalam batch ini (diterima dulu baru di return jika ada selisih)');
            $table->dateTime('goods_receive_detail_created_datetime')->comment('Waktu pembuatan baris detail');

            $table->index('goods_receive_detail_receive_id');
            $table->index('goods_receive_detail_product_id');
            $table->index('goods_receive_detail_expire_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receive_detail');
    }
};
