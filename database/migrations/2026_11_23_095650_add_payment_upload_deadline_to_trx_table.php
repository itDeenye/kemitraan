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
            $table->boolean('trx_payment_upload_timeout_enabled')->default(false);
            $table->dateTime('trx_payment_upload_expires_at')->nullable();
            $table->index(['trx_status', 'trx_payment_upload_expires_at'], 'trx_payment_upload_expiry_index');
            $table->enum('trx_status', [
                'rejected', 'waiting_stock_screening', 'waiting_payment', 'waiting_payment_approval',
                'cancelled', 'expired', 'processing', 'shipped', 'reship_required', 'ready_to_pickup',
                'received', 'completed',
            ])->default('waiting_payment')->comment('Status transaksi termasuk batas waktu pembayaran')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('trx')->where('trx_status', 'expired')->update(['trx_status' => 'cancelled']);
        Schema::table('trx', function (Blueprint $table) {
            $table->dropIndex('trx_payment_upload_expiry_index');
            $table->dropColumn(['trx_payment_upload_timeout_enabled', 'trx_payment_upload_expires_at']);
            $table->enum('trx_status', [
                'rejected', 'waiting_stock_screening', 'waiting_payment', 'waiting_payment_approval',
                'cancelled', 'processing', 'shipped', 'reship_required', 'ready_to_pickup',
                'received', 'completed',
            ])->default('waiting_payment')->comment('Status transaksi termasuk screening stok distributor ke perusahaan')->change();
        });
    }
};
