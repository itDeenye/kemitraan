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
        Schema::table('return', function (Blueprint $table) {
            $table->unsignedInteger('return_goods_receive_id')
                ->nullable()
                ->after('return_trx_id')
                ->comment('ID penerimaan barang asal retur');

            $table->index('return_goods_receive_id', 'idx_return_goods_receive_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return', function (Blueprint $table) {
            $table->dropIndex('idx_return_goods_receive_id');
            $table->dropColumn('return_goods_receive_id');
        });
    }
};
