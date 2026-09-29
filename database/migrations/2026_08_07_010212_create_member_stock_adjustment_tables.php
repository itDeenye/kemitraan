<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_stock_adjustment', function (Blueprint $table) {
            $table->comment('Dokumen penyesuaian stok member yang dibuat oleh administrator.');
            $table->increments('stock_adjustment_id')->comment('ID Stock Adjustment');
            $table->unsignedInteger('stock_adjustment_administrator_id')->comment('ID Admin');
            $table->unsignedInteger('stock_adjustment_member_id')->comment('ID Member');
            $table->string('stock_adjustment_code', 50)->comment('Kode Stock Adjustment');
            $table->text('stock_adjustment_note')->comment('Catatan');
            $table->dateTime('stock_adjustment_datetime')->nullable()->comment('Tanggal Stock Adjustment');

            $table->unique('stock_adjustment_code', 'member_stock_adjustment_code_unique');
            $table->index('stock_adjustment_administrator_id', 'idx_member_stock_adjustment_admin');
            $table->index('stock_adjustment_member_id', 'idx_member_stock_adjustment_member');
            $table->index('stock_adjustment_datetime', 'idx_member_stock_adjustment_date');
        });

        Schema::create('member_stock_adjustment_detail', function (Blueprint $table) {
            $table->comment('Rincian produk yang ditambah atau dikurangi pada penyesuaian stok member.');
            $table->increments('stock_adjustment_detail_id')->comment('ID Stock Adjustment Detail');
            $table->unsignedInteger('stock_adjustment_detail_stock_adjustment_id')->comment('ID Stock Adjustment');
            $table->unsignedInteger('stock_adjustment_detail_stock_member_id')->comment('ID Stok Member');
            $table->unsignedInteger('stock_adjustment_detail_product_id')->comment('ID Produk');
            $table->enum('stock_adjustment_detail_type', ['in', 'out'])->comment('Tipe Stock');
            $table->unsignedInteger('stock_adjustment_detail_qty')->comment('Kuantitas');
            $table->unsignedInteger('stock_adjustment_detail_current_price')->default(0)->comment('Harga Saat Itu');
            $table->text('stock_adjustment_detail_note')->comment('Catatan');

            $table->index('stock_adjustment_detail_product_id', 'idx_member_stock_adjustment_detail_product');
            $table->index('stock_adjustment_detail_stock_adjustment_id', 'idx_member_stock_adjustment_detail_header');
            $table->index('stock_adjustment_detail_stock_member_id', 'idx_member_stock_adjustment_detail_stock');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_stock_adjustment_detail');
        Schema::dropIfExists('member_stock_adjustment');
    }
};
