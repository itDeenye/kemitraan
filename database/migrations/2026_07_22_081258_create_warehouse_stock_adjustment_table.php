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
        Schema::create('warehouse_stock_adjustment', function (Blueprint $table) {
            $table->comment('Dokumen penyesuaian stok warehouse beserta alasan dan administrator pembuat.');
            $table->increments('stock_adjustment_id')->comment('ID penyesuaian stok');
            $table->unsignedInteger('stock_adjustment_administrator_id')->comment('ID administrator pembuat');
            $table->unsignedInteger('stock_adjustment_warehouse_id')->comment('ID warehouse');
            $table->string('stock_adjustment_code', 50)->comment('Kode unik penyesuaian stok');
            $table->text('stock_adjustment_note')->comment('Alasan penyesuaian stok');
            $table->dateTime('stock_adjustment_datetime')->nullable()->comment('Waktu penyesuaian stok');

            $table->unique('stock_adjustment_code');
            $table->index('stock_adjustment_administrator_id', 'idx_stock_adjustment_admin');
            $table->index('stock_adjustment_warehouse_id', 'idx_stock_adjustment_warehouse');
            $table->index('stock_adjustment_datetime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_stock_adjustment');
    }
};
