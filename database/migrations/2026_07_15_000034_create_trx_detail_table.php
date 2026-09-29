<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: trx_detail
 * Modul: Transaksi
 *
 * Line items/item detail yang dibeli dalam satu transaksi.
 * Menyimpan snapshot data produk saat transaksi (harga, berat, dan dimensi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trx_detail', function (Blueprint $table) {
            $table->comment('Line items/item detail yang dibeli dalam satu transaksi. Menyimpan snapshot harga, berat, dan dimensi produk. Poin dihitung dari kuantitas: 1 pcs = 1 poin.');
            $table->increments('trx_detail_id')->comment('ID detail transaksi');
            $table->unsignedInteger('trx_detail_trx_id')->comment('ID transaksi');
            $table->unsignedInteger('trx_detail_product_id')->comment('ID produk');
            $table->unsignedInteger('trx_detail_product_plan_id')->default(0)->comment('ID plan dari mlm_plan');
            $table->enum('trx_detail_product_type', ['activation', 'upgrade', 'cloning', 'registration', 'stock'])->default('stock')->comment('Tipe produk');
            $table->string('trx_detail_product_code', 20)->comment('Kode produk');
            $table->string('trx_detail_product_name', 50)->comment('Nama produk');
            $table->unsignedInteger('trx_detail_product_price')->default(0)->comment('Harga produk');
            $table->unsignedInteger('trx_detail_product_weight')->default(0)->comment('Berat produk (gram)');
            $table->decimal('trx_detail_product_length', 7, 0)->unsigned()->default(0)->comment('Panjang produk (cm)');
            $table->decimal('trx_detail_product_height', 7, 0)->unsigned()->default(0)->comment('Tinggi produk (cm)');
            $table->decimal('trx_detail_product_width', 7, 0)->unsigned()->default(0)->comment('Lebar produk (cm)');
            $table->decimal('trx_detail_discount_percent', 4, 0)->unsigned()->default(0)->comment('Persentase diskon per 1 qty');
            $table->unsignedInteger('trx_detail_discount_value')->default(0)->comment('Nilai diskon per 1 qty');
            $table->unsignedInteger('trx_detail_nett_price')->default(0)->comment('Harga setelah diskon');
            $table->unsignedInteger('trx_detail_qty')->default(0)->comment('Jumlah/kuantitas produk');

            $table->index('trx_detail_trx_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_detail');
    }
};
