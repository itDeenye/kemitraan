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
        Schema::table('trx', function (Blueprint $table): void {
            $table->enum('trx_status', [
                'rejected',
                'waiting_payment',
                'waiting_payment_approval',
                'cancelled',
                'processing',
                'shipped',
                'reship_required',
                'ready_to_pickup',
                'received',
                'completed',
            ])->default('waiting_payment')
                ->comment('Status transaksi termasuk kebutuhan pengiriman ulang')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('trx')
            ->where('trx_status', 'reship_required')
            ->update([
                'trx_status' => 'shipped',
                'trx_status_datetime' => now(),
            ]);

        Schema::table('trx', function (Blueprint $table): void {
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
            ])->default('waiting_payment')
                ->comment('Status transaksi tanpa persetujuan pesanan dan tanpa tahap pengemasan')
                ->change();
        });
    }
};
