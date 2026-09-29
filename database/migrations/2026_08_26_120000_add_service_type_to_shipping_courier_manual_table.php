<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_courier_manual', function (Blueprint $table): void {
            $table->string('shipping_courier_manual_type', 30)
                ->default('')
                ->after('shipping_courier_manual_service')
                ->comment('Kode tipe layanan kurir dari hasil tarif, contoh EZ atau REG23');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_courier_manual', function (Blueprint $table): void {
            $table->dropColumn('shipping_courier_manual_type');
        });
    }
};
