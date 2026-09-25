<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_stock', function (Blueprint $table): void {
            $table->integer('warehouse_stock_balance')
                ->default(0)
                ->comment('Sisa saldo stok perusahaan; dapat negatif karena transaksi pembelian')
                ->change();
        });

        Schema::table('warehouse_stock_log', function (Blueprint $table): void {
            $table->integer('warehouse_stock_log_balance')
                ->default(0)
                ->comment('Saldo akhir stok perusahaan setelah mutasi')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('warehouse_stock')
            ->where('warehouse_stock_balance', '<', 0)
            ->update(['warehouse_stock_balance' => 0]);
        DB::table('warehouse_stock_log')
            ->where('warehouse_stock_log_balance', '<', 0)
            ->update(['warehouse_stock_log_balance' => 0]);

        Schema::table('warehouse_stock', function (Blueprint $table): void {
            $table->unsignedInteger('warehouse_stock_balance')
                ->default(0)
                ->comment('Sisa Saldo Stok (Pcs)')
                ->change();
        });

        Schema::table('warehouse_stock_log', function (Blueprint $table): void {
            $table->unsignedInteger('warehouse_stock_log_balance')
                ->default(0)
                ->comment('Saldo Akhir Setelah Mutasi (Pcs)')
                ->change();
        });
    }
};
