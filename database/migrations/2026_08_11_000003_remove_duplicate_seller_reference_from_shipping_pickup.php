<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipping_pickup')) {
            return;
        }

        Schema::table('shipping_pickup', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_pickup_seller_ref_type',
                'shipping_pickup_seller_ref_id',
            ]);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shipping_pickup')) {
            return;
        }

        Schema::table('shipping_pickup', function (Blueprint $table) {
            $table->enum('shipping_pickup_seller_ref_type', ['warehouse', 'stockist'])
                ->default('stockist')
                ->after('shipping_pickup_ref_id')
                ->comment('Referensi seller lama; sumber utama seller adalah transaksi');
            $table->unsignedInteger('shipping_pickup_seller_ref_id')
                ->default(0)
                ->after('shipping_pickup_seller_ref_type')
                ->comment('Referensi seller lama; sumber utama seller adalah transaksi');
        });
    }
};
