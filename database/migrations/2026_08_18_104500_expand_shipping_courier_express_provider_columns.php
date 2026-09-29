<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_courier_express', function (Blueprint $table) {
            $table->string('shipping_courier_express_type', 30)
                ->default('')
                ->comment('Tipe layanan kurir dari provider')
                ->change();
            $table->string('shipping_courier_express_expedition_name', 30)
                ->default('')
                ->comment('Kode kurir dari provider')
                ->change();
            $table->string('shipping_courier_express_expedition_service', 100)
                ->default('')
                ->comment('Nama layanan kurir dari provider')
                ->change();
            $table->string('shipping_courier_express_etd', 30)
                ->default('')
                ->comment('Estimasi pengiriman dari provider')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('shipping_courier_express', function (Blueprint $table) {
            $table->string('shipping_courier_express_type', 10)
                ->default('')
                ->comment('Tipe ekspedisi')
                ->change();
            $table->string('shipping_courier_express_expedition_name', 20)
                ->default('')
                ->comment('Nama kurir (JNE/J&T/Sicepat)')
                ->change();
            $table->string('shipping_courier_express_expedition_service', 10)
                ->default('')
                ->comment('Layanan kurir (REG/OKE/YES)')
                ->change();
            $table->string('shipping_courier_express_etd', 20)
                ->default('')
                ->comment('Estimasi pengiriman (hari/jam)')
                ->change();
        });
    }
};
