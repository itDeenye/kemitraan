<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: promotion
 * Modul: Produk & Harga
 *
 * Master program promo (diskon, bundling, dll).
 * Tabel baru khusus DNY untuk mengelola promo produk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion', function (Blueprint $table) {
            $table->comment('Master program promo (diskon, bundling, dll). Tabel baru khusus DNY untuk mengelola promo produk.');
            $table->increments('promotion_id')->comment('ID Promo');
            $table->string('promotion_name', 200)->default('')->comment('Nama program promo');
            $table->enum('promotion_type', ['discount', 'bundling'])->comment('Jenis promo');
            $table->unsignedInteger('promotion_value')->default(0)->comment('Nilai diskon');
            $table->date('promotion_start_date')->comment('Tanggal mulai promo');
            $table->date('promotion_end_date')->comment('Tanggal berakhir promo');
            $table->text('promotion_terms')->nullable()->comment('Syarat & ketentuan promo');
            $table->unsignedTinyInteger('promotion_is_active')->default(1)->comment('Status aktif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion');
    }
};
