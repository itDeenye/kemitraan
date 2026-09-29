<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: product
 * Modul: Produk & Harga
 *
 * Daftar produk/barang di katalog DNY. Setiap produk memiliki
 * kode unik, kategori, harga, berat, dimensi, dan status.
 * Diacu oleh `product_price`, `trx_detail`, `member_stock`,
 * `product_stock_batch`, `product_stock_log`, dan `return_detail`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product', function (Blueprint $table) {
            $table->comment('Daftar produk/barang di katalog DNY. Setiap produk memiliki kode unik, kategori, harga, berat, dimensi, dan status. Diacu oleh `product_price`, `trx_detail`, `member_stock`, `product_stock_batch`, `product_stock_log`, dan `return_detail`.');
            $table->increments('product_id')->comment('ID unik produk');
            $table->unsignedInteger('product_product_category_id')->default(0)->comment('ID Kategori Produk');
            $table->string('product_code', 20)->default('')->comment('Kode/SKU produk');
            $table->string('product_name', 50)->default('')->comment('Nama produk');
            $table->text('product_description')->nullable()->comment('Deskripsi produk');
            $table->string('product_image', 255)->default('')->comment('Path gambar produk');
            $table->string('product_image_filename', 255)->default('')->comment('Nama file gambar produk');
            $table->unsignedInteger('product_customer_price')->default(0)->comment('Harga jual eceran / HET untuk End User');
            $table->unsignedInteger('product_distributor_price')->default(0)->comment('Harga untuk level Distributor');
            $table->unsignedInteger('product_agent_price')->default(0)->comment('Harga untuk level Agent');
            $table->unsignedInteger('product_reseller_price')->default(0)->comment('Harga untuk level Reseller');
            $table->unsignedInteger('product_weight')->default(0)->comment('Berat produk (gram)');
            $table->decimal('product_length', 7, 0)->unsigned()->default(0)->comment('Panjang produk (cm)');
            $table->decimal('product_height', 7, 0)->unsigned()->default(0)->comment('Tinggi produk (cm)');
            $table->decimal('product_width', 7, 0)->unsigned()->default(0)->comment('Lebar produk (cm)');
            $table->string('product_unit', 30)->default('pcs')->comment('Satuan produk (pcs, box, tube)');
            $table->unsignedTinyInteger('product_is_publish')->default(1)->comment('Status tampil di publik (1:tampil; 0:tidak)');
            $table->unsignedTinyInteger('product_is_active')->default(1)->comment('Status aktif');
            $table->unsignedTinyInteger('product_is_deleted')->default(0)->comment('Soft delete flag (0=active, 1=deleted)');
            $table->dateTime('product_input_datetime')->nullable()->comment('Tanggal input');

            $table->index('product_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product');
    }
};
