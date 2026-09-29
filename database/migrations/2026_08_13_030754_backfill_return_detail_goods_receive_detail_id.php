<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('return_detail')
            ->whereNull('return_detail_goods_receive_detail_id')
            ->orderBy('return_detail_id')
            ->chunkById(100, function ($details): void {
                foreach ($details as $detail) {
                    $goodsReceiveId = DB::table('return')
                        ->where('return_id', $detail->return_detail_return_id)
                        ->value('return_goods_receive_id');
                    if (! $goodsReceiveId) {
                        continue;
                    }

                    $goodsReceiveDetailId = DB::table('goods_receive_detail')
                        ->where('goods_receive_detail_receive_id', $goodsReceiveId)
                        ->where('goods_receive_detail_product_id', $detail->return_detail_product_id)
                        ->orderBy('goods_receive_detail_id')
                        ->value('goods_receive_detail_id');
                    if (! $goodsReceiveDetailId) {
                        continue;
                    }

                    DB::table('return_detail')
                        ->where('return_detail_id', $detail->return_detail_id)
                        ->update([
                            'return_detail_goods_receive_detail_id' => $goodsReceiveDetailId,
                        ]);
                }
            }, 'return_detail_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('return_detail')->update([
            'return_detail_goods_receive_detail_id' => null,
        ]);
    }
};
