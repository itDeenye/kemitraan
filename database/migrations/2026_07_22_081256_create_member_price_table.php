<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_price', function (Blueprint $table) {
            $table->comment('Harga produk untuk setiap level member.');
            $table->increments('member_price_id')->comment('ID harga member');
            $table->unsignedInteger('member_price_product_id')->comment('ID produk');
            $table->unsignedInteger('member_price_member_level_id')->comment('ID level member');
            $table->unsignedInteger('member_price_value')->default(0)->comment('Harga produk untuk level member');
            $table->dateTime('member_price_created_datetime')->nullable()->comment('Waktu dibuat');
            $table->dateTime('member_price_updated_datetime')->nullable()->comment('Waktu diperbarui');

            $table->unique(
                ['member_price_product_id', 'member_price_member_level_id'],
                'uq_member_price_product_level'
            );
            $table->index('member_price_member_level_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_price');
    }
};
