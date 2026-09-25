<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trx')) {
            return;
        }

        DB::table('trx')
            ->where('trx_status', 'waiting_order_approval')
            ->update(['trx_status' => 'waiting_payment']);
        DB::table('trx')
            ->where('trx_status', 'picked_up')
            ->update(['trx_status' => 'ready_to_pickup']);

        Schema::table('trx', function (Blueprint $table) {
            $table->enum('trx_status', [
                'rejected',
                'waiting_payment',
                'waiting_payment_approval',
                'cancelled',
                'processing',
                'shipped',
                'ready_to_pickup',
                'received',
                'completed',
            ])->default('waiting_payment')->comment('Status transaksi tanpa persetujuan pesanan dan tanpa tahap pengemasan')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trx')) {
            return;
        }

        Schema::table('trx', function (Blueprint $table) {
            $table->enum('trx_status', [
                'waiting_order_approval',
                'rejected',
                'waiting_payment',
                'waiting_payment_approval',
                'cancelled',
                'processing',
                'shipped',
                'ready_to_pickup',
                'picked_up',
                'received',
                'completed',
            ])->default('waiting_order_approval')->comment('Status transaksi')->change();
        });
    }
};
