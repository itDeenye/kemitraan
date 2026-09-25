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
        Schema::create('warehouse_stock_adjustment_detail', function (Blueprint $table) {
            $table->comment('Rincian produk yang ditambah atau dikurangi pada penyesuaian stok warehouse.');
            $table->increments('stock_adjustment_detail_id')->comment('ID detail penyesuaian stok');
            $table->unsignedInteger('stock_adjustment_detail_stock_adjustment_id')->comment('ID header penyesuaian stok');
            $table->unsignedInteger('stock_adjustment_detail_stock_warehouse_id')->comment('ID saldo stok warehouse');
            $table->unsignedInteger('stock_adjustment_detail_product_id')->comment('ID produk');
            $table->enum('stock_adjustment_detail_type', ['in', 'out'])->comment('Tipe penyesuaian stok');
            $table->unsignedInteger('stock_adjustment_detail_qty')->comment('Jumlah penyesuaian');
            $table->unsignedInteger('stock_adjustment_detail_current_price')->default(0)->comment('Harga produk saat penyesuaian');
            $table->text('stock_adjustment_detail_note')->nullable()->comment('Catatan detail');

            $table->index('stock_adjustment_detail_stock_adjustment_id', 'idx_stock_adjustment_detail_header');
            $table->index('stock_adjustment_detail_stock_warehouse_id', 'idx_stock_adjustment_detail_stock');
            $table->index('stock_adjustment_detail_product_id', 'idx_stock_adjustment_detail_product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_stock_adjustment_detail');
    }
};
