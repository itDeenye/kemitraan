<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_courier_manual', function (Blueprint $table) {
            $table->string('shipping_courier_manual_name', 30)
                ->default('')
                ->comment('Kode kurir dari hasil tarif provider')
                ->change();
            $table->string('shipping_courier_manual_service', 100)
                ->default('')
                ->comment('Nama layanan kurir dari hasil tarif provider')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('shipping_courier_manual', function (Blueprint $table) {
            $table->string('shipping_courier_manual_name', 50)
                ->default('')
                ->comment('Nama kurir/ekspedisi')
                ->change();
            $table->string('shipping_courier_manual_service', 50)
                ->default('')
                ->comment('Layanan pengiriman')
                ->change();
        });
    }
};
