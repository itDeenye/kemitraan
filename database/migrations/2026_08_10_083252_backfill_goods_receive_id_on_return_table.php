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
        DB::table('return')
            ->select(['return_id', 'return_trx_id'])
            ->whereNull('return_goods_receive_id')
            ->orderBy('return_id')
            ->each(function (object $return): void {
                $goodsReceiveId = DB::table('goods_receive')
                    ->where('goods_receive_trx_id', $return->return_trx_id)
                    ->where('goods_receive_status', 'completed')
                    ->latest('goods_receive_id')
                    ->value('goods_receive_id');

                if ($goodsReceiveId === null) {
                    return;
                }

                DB::table('return')
                    ->where('return_id', $return->return_id)
                    ->update(['return_goods_receive_id' => $goodsReceiveId]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Backfill data tidak dibatalkan agar relasi penerimaan retur yang valid tidak hilang.
    }
};
