<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trx')) {
            return;
        }

        if (Schema::hasColumn('trx', 'trx_shipping_charge') && ! Schema::hasColumn('trx', 'trx_shipping_cost')) {
            Schema::table('trx', function (Blueprint $table) {
                $table->renameColumn('trx_shipping_charge', 'trx_shipping_cost');
            });
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
                'delivered',
                'ready_to_pickup',
                'picked_up',
                'received',
                'completed',
            ])->default('waiting_order_approval')->comment('Status transaksi')->change();
        });

        if (Schema::hasColumn('trx', 'trx_shipping_cost') && ! Schema::hasColumn('trx', 'trx_shipping_charge')) {
            Schema::table('trx', function (Blueprint $table) {
                $table->renameColumn('trx_shipping_cost', 'trx_shipping_charge');
            });
        }
    }
};
