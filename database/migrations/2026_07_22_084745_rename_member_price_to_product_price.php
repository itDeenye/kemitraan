<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('member_price', 'product_price');

        Schema::table('product_price', function (Blueprint $table) {
            $table->renameColumn('member_price_id', 'product_price_id');
            $table->renameColumn('member_price_product_id', 'product_price_product_id');
            $table->renameColumn('member_price_member_level_id', 'product_price_member_level_id');
            $table->renameColumn('member_price_value', 'product_price_value');
            $table->renameColumn('member_price_created_datetime', 'product_price_created_datetime');
            $table->renameColumn('member_price_updated_datetime', 'product_price_updated_datetime');
        });
    }

    public function down(): void
    {
        Schema::table('product_price', function (Blueprint $table) {
            $table->renameColumn('product_price_id', 'member_price_id');
            $table->renameColumn('product_price_product_id', 'member_price_product_id');
            $table->renameColumn('product_price_member_level_id', 'member_price_member_level_id');
            $table->renameColumn('product_price_value', 'member_price_value');
            $table->renameColumn('product_price_created_datetime', 'member_price_created_datetime');
            $table->renameColumn('product_price_updated_datetime', 'member_price_updated_datetime');
        });

        Schema::rename('product_price', 'member_price');
    }
};
