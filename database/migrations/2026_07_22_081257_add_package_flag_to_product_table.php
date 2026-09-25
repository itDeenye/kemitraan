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
        Schema::table('product', function (Blueprint $table) {
            $table->unsignedTinyInteger('product_is_package')
                ->default(0)
                ->after('product_unit')
                ->comment('Penanda produk merupakan paket produk');
            $table->index('product_is_package');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product', function (Blueprint $table) {
            $table->dropIndex(['product_is_package']);
            $table->dropColumn('product_is_package');
        });
    }
};
