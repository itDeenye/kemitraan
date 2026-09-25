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
        Schema::table('trx_detail', function (Blueprint $table) {
            $table->string('trx_detail_product_name', 200)
                ->comment('Nama produk saat transaksi')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx_detail', function (Blueprint $table) {
            $table->string('trx_detail_product_name', 50)
                ->comment('Nama produk saat transaksi')
                ->change();
        });
    }
};
