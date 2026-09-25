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
        if (Schema::hasColumn('product', 'product_pv')) {
            Schema::table('product', function (Blueprint $table) {
                $table->dropColumn('product_pv');
            });
        }

        if (Schema::hasColumn('trx_detail', 'trx_detail_product_pv')) {
            Schema::table('trx_detail', function (Blueprint $table) {
                $table->dropColumn('trx_detail_product_pv');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('product', 'product_pv')) {
            Schema::table('product', function (Blueprint $table) {
                $table->unsignedInteger('product_pv')
                    ->default(0)
                    ->after('product_customer_price')
                    ->comment('Point Value produk');
            });
        }

        if (! Schema::hasColumn('trx_detail', 'trx_detail_product_pv')) {
            Schema::table('trx_detail', function (Blueprint $table) {
                $table->unsignedInteger('trx_detail_product_pv')
                    ->default(0)
                    ->after('trx_detail_product_price')
                    ->comment('Poin produk');
            });
        }
    }
};
