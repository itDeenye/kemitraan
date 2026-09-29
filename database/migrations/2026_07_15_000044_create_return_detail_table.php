<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: return_detail
 * Modul: Retur
 *
 * Detail item produk yang diretur dan alasan spesifiknya per barang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_detail', function (Blueprint $table) {
            $table->comment('Detail item produk yang diretur dan alasan spesifiknya per barang.');
            $table->increments('return_detail_id')->comment('ID Detail Return');
            $table->unsignedInteger('return_detail_return_id')->comment('ID Return');
            $table->unsignedInteger('return_detail_product_id')->comment('ID Produk');
            $table->unsignedInteger('return_detail_qty')->default(0)->comment('Jumlah diretur');
            $table->string('return_detail_reason', 500)->default('')->comment('Alasan spesifik per item');

            $table->index('return_detail_return_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_detail');
    }
};
