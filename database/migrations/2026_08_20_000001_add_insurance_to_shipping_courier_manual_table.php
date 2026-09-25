<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_courier_manual', function (Blueprint $table): void {
            $table->unsignedInteger('shipping_courier_manual_insurance')
                ->default(0)
                ->after('shipping_courier_manual_price')
                ->comment('Biaya asuransi pengiriman (Rp)');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_courier_manual', function (Blueprint $table): void {
            $table->dropColumn('shipping_courier_manual_insurance');
        });
    }
};
