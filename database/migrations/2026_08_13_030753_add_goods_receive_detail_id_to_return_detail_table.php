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
        Schema::table('return_detail', function (Blueprint $table) {
            $table->unsignedInteger('return_detail_goods_receive_detail_id')
                ->nullable()
                ->after('return_detail_return_id')
                ->comment('ID batch produk dari detail penerimaan barang');
            $table->index(
                'return_detail_goods_receive_detail_id',
                'idx_return_detail_goods_receive_detail',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_detail', function (Blueprint $table) {
            $table->dropIndex('idx_return_detail_goods_receive_detail');
            $table->dropColumn('return_detail_goods_receive_detail_id');
        });
    }
};
