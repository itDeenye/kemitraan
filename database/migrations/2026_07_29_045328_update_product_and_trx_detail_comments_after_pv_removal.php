<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `product` COMMENT = 'Daftar produk katalog DNY dengan kategori, harga, berat, dimensi, status, dan penanda paket produk.'");
        DB::statement("ALTER TABLE `trx_detail` COMMENT = 'Detail item transaksi dan snapshot produk. Jumlah poin mengikuti kuantitas: 1 pcs = 1 poin.'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `product` COMMENT = 'Daftar produk katalog DNY dengan kategori, harga, berat, dimensi, dan status.'");
        DB::statement("ALTER TABLE `trx_detail` COMMENT = 'Detail item transaksi dan snapshot harga, berat, serta dimensi produk.'");
    }
};
