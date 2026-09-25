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
        Schema::table('warehouse_stock_adjustment_detail', function (Blueprint $table): void {
            $table->string('stock_adjustment_detail_batch_number', 50)
                ->nullable()
                ->after('stock_adjustment_detail_product_id')
                ->comment('Nomor batch produk yang disesuaikan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_stock_adjustment_detail', function (Blueprint $table): void {
            $table->dropColumn('stock_adjustment_detail_batch_number');
        });
    }
};
