<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trx', function (Blueprint $table): void {
            $table->enum('trx_preorder_fulfillment_mode', ['direct', 'upline_transit'])
                ->default('direct')
                ->after('trx_is_preorder')
                ->comment('Pemenuhan PO pickup: langsung di seller atau dikirim dulu ke upline langsung');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx', function (Blueprint $table): void {
            $table->dropColumn('trx_preorder_fulfillment_mode');
        });
    }
};
