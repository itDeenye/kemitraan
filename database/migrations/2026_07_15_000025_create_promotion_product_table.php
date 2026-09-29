<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: promotion_product
 * Modul: Produk & Harga
 *
 * Pivot table M:N antara `promotion` dan `product`.
 * Mencatat produk yang termasuk program promo beserta qty & diskon per produk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_product', function (Blueprint $table) {
            $table->comment('Pivot table M:N antara `promotion` dan `product`. Mencatat produk yang termasuk program promo beserta qty & diskon per produk.');
            $table->increments('promotion_product_id')->comment('ID Pivot Promo Produk');
            $table->unsignedInteger('promotion_product_promotion_id')->comment('ID Promo');
            $table->unsignedInteger('promotion_product_product_id')->comment('ID Produk');
            $table->unsignedInteger('promotion_product_qty')->default(1)->comment('Qty untuk bundling');
            $table->decimal('promotion_product_discount_percent', 5, 2)->unsigned()->default(0)->comment('Persentase diskon per produk');

            $table->index('promotion_product_promotion_id', 'idx_promo_product_promo');
            $table->index('promotion_product_product_id', 'idx_promo_product_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_product');
    }
};
