<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_price', function (Blueprint $table) {
            $table->renameIndex(
                'member_price_member_price_member_level_id_index',
                'product_price_member_level_id_index',
            );
            $table->renameIndex(
                'uq_member_price_product_level',
                'uq_product_price_product_level',
            );
        });
    }

    public function down(): void
    {
        Schema::table('product_price', function (Blueprint $table) {
            $table->renameIndex(
                'product_price_member_level_id_index',
                'member_price_member_price_member_level_id_index',
            );
            $table->renameIndex(
                'uq_product_price_product_level',
                'uq_member_price_product_level',
            );
        });
    }
};
