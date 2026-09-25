<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: product_category
 * Modul: Produk & Harga
 *
 * Kategori/klasifikasi produk (skincare, supplement, dll).
 * Setiap produk di `product` merujuk ke satu kategori.
 * Digunakan untuk filter dan navigasi katalog.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_category', function (Blueprint $table) {
            $table->comment('Kategori/klasifikasi produk (skincare, supplement, dll). Setiap produk di `product` merujuk ke satu kategori. Digunakan untuk filter dan navigasi katalog.');
            $table->increments('product_category_id')->comment('ID Kategori Produk');
            $table->string('product_category_name', 100)->default('')->comment('Nama kategori');
            $table->string('product_category_description', 255)->default('')->comment('Deskripsi singkat kategori');
            $table->unsignedTinyInteger('product_category_is_active')->default(1)->comment('Status aktif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_category');
    }
};
