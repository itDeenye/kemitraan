<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return', function (Blueprint $table) {
            $table->unsignedInteger('return_replacement_trx_id')
                ->nullable()
                ->after('return_approved_by')
                ->unique('return_replacement_trx_unique')
                ->comment('ID transaksi pengiriman barang pengganti setelah retur disetujui');
        });

        Schema::table('trx', function (Blueprint $table) {
            $table->enum('trx_type', [
                'registration',
                'activation',
                'stock',
                'retail',
                'upgrade',
                'return_replacement',
            ])->comment('Jenis transaksi')->change();
            $table->enum('trx_status', [
                'waiting_order_approval',
                'rejected',
                'waiting_payment',
                'waiting_payment_approval',
                'cancelled',
                'processing',
                'packing',
                'shipped',
                'ready_to_pickup',
                'picked_up',
                'received',
                'completed',
            ])->default('waiting_order_approval')->comment('Status transaksi')->change();
        });
    }

    public function down(): void
    {
        DB::table('trx')
            ->where('trx_type', 'return_replacement')
            ->update(['trx_type' => 'stock']);

        Schema::table('trx', function (Blueprint $table) {
            $table->enum('trx_type', [
                'registration',
                'activation',
                'stock',
                'retail',
                'upgrade',
            ])->comment('Jenis transaksi')->change();
            $table->enum('trx_status', [
                'waiting_order_approval',
                'rejected',
                'waiting_payment',
                'waiting_payment_approval',
                'cancelled',
                'processing',
                'packing',
                'shipped',
                'ready_to_pickup',
                'picked_up',
                'received',
                'completed',
            ])->default('waiting_order_approval')->comment('Status transaksi')->change();
        });

        Schema::table('return', function (Blueprint $table) {
            $table->dropUnique('return_replacement_trx_unique');
            $table->dropColumn('return_replacement_trx_id');
        });
    }
};
