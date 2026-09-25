<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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
                'delivered',
                'ready_to_pickup',
                'picked_up',
                'received',
                'completed',
            ])->default('waiting_order_approval')->comment('Status transaksi')->change();
            $table->index('trx_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('trx')->where('trx_status', 'waiting_order_approval')->update(['trx_status' => 'waiting_payment']);
        DB::table('trx')->where('trx_status', 'rejected')->update(['trx_status' => 'cancelled']);
        DB::table('trx')->where('trx_status', 'packing')->update(['trx_status' => 'processing']);
        DB::table('trx')->where('trx_status', 'shipped')->update(['trx_status' => 'delivered']);

        Schema::table('trx', function (Blueprint $table) {
            $table->dropIndex(['trx_status']);
            $table->enum('trx_status', [
                'waiting_payment',
                'waiting_payment_approval',
                'cancelled',
                'processing',
                'delivered',
                'ready_to_pickup',
                'picked_up',
                'received',
                'completed',
            ])->default('waiting_payment')->comment('Status transaksi')->change();
        });
    }
};
