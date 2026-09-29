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
            ->where('trx_status', 'packing')
            ->update(['trx_status' => 'processing']);

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
            ])->default('waiting_order_approval')->comment('Status transaksi tanpa tahap pengemasan; setelah processing langsung dikirim')->change();
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
                'packing',
                'shipped',
                'ready_to_pickup',
                'picked_up',
                'received',
                'completed',
            ])->default('waiting_order_approval')->comment('Status transaksi')->change();
        });
    }
};
