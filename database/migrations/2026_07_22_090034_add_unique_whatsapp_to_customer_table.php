<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customer')
            ->where('customer_whatsapp', '')
            ->update(['customer_whatsapp' => null]);

        Schema::table('customer', function (Blueprint $table) {
            $table->string('customer_whatsapp', 20)
                ->nullable()
                ->default(null)
                ->comment('Nomor WhatsApp unik pelanggan')
                ->change();
            $table->unique('customer_whatsapp', 'uq_customer_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('customer', function (Blueprint $table) {
            $table->dropUnique('uq_customer_whatsapp');
        });

        DB::table('customer')
            ->whereNull('customer_whatsapp')
            ->update(['customer_whatsapp' => '']);

        Schema::table('customer', function (Blueprint $table) {
            $table->string('customer_whatsapp', 20)
                ->default('')
                ->nullable(false)
                ->comment('Nomor WhatsApp')
                ->change();
        });
    }
};
