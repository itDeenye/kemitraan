<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product', function (Blueprint $table) {
            $table->string('product_bpom_number', 50)
                ->nullable()
                ->after('product_name')
                ->comment('Nomor izin edar BPOM produk');
        });

        Schema::table('trx_detail', function (Blueprint $table) {
            $table->string('trx_detail_product_bpom_number', 50)
                ->nullable()
                ->after('trx_detail_product_name')
                ->comment('Snapshot nomor izin edar BPOM produk saat transaksi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx_detail', function (Blueprint $table) {
            $table->dropColumn('trx_detail_product_bpom_number');
        });

        Schema::table('product', function (Blueprint $table) {
            $table->dropColumn('product_bpom_number');
        });
    }
};
