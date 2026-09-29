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
                'waiting_stock_screening',
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
                ->comment('Status transaksi termasuk screening stok distributor ke perusahaan')
                ->change();
        });

        Schema::create('trx_stock_screening', function (Blueprint $table): void {
            $table->increments('stock_screening_id');
            $table->unsignedInteger('stock_screening_trx_id')->unique();
            $table->enum('stock_screening_status', ['pending', 'approved', 'rejected', 'cancelled'])
                ->default('pending');
            $table->unsignedInteger('stock_screening_administrator_id')->default(0);
            $table->text('stock_screening_note')->nullable();
            $table->dateTime('stock_screening_requested_datetime');
            $table->dateTime('stock_screening_reviewed_datetime')->nullable();

            $table->index(
                ['stock_screening_status', 'stock_screening_requested_datetime'],
                'trx_stock_screening_status_requested_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('trx')
            ->where('trx_status', 'waiting_stock_screening')
            ->update([
                'trx_status' => 'waiting_payment',
                'trx_status_datetime' => now(),
            ]);

        Schema::dropIfExists('trx_stock_screening');

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
};
