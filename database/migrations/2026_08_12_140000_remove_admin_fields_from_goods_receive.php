<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $adminReceiveIds = DB::table('goods_receive')
            ->where('goods_receive_trx_id', 0)
            ->where('goods_receive_buyer_type', 'warehouse')
            ->pluck('goods_receive_id');

        if ($adminReceiveIds->isNotEmpty()) {
            DB::table('goods_receive_detail')
                ->whereIn('goods_receive_detail_receive_id', $adminReceiveIds)
                ->delete();
            DB::table('goods_receive')
                ->whereIn('goods_receive_id', $adminReceiveIds)
                ->delete();
        }

        Schema::table('goods_receive', function (Blueprint $table) {
            $table->dropIndex('idx_goods_receive_admin');
            $table->dropColumn([
                'goods_receive_administrator_id',
                'goods_receive_supplier_name',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('goods_receive', function (Blueprint $table) {
            $table->unsignedInteger('goods_receive_administrator_id')
                ->nullable()
                ->after('goods_receive_id')
                ->comment('ID administrator pembuat penerimaan warehouse');
            $table->string('goods_receive_supplier_name', 150)
                ->nullable()
                ->after('goods_receive_seller_id')
                ->comment('Nama supplier pengirim barang ke warehouse');
            $table->index('goods_receive_administrator_id', 'idx_goods_receive_admin');
        });
    }
};
