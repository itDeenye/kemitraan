<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_detail', function (Blueprint $table): void {
            $table->unsignedInteger('return_detail_received_qty')
                ->default(0)
                ->after('return_detail_qty')
                ->comment('Jumlah barang retur yang benar-benar diterima Company');
        });

        DB::table('return_detail')
            ->join('return', 'return.return_id', '=', 'return_detail.return_detail_return_id')
            ->whereIn('return.return_status', [
                'received_by_company',
                'replacement_in_transit',
                'replacement_shipping_failed',
                'completed',
            ])
            ->update([
                'return_detail.return_detail_received_qty' => DB::raw('return_detail.return_detail_qty'),
            ]);
    }

    public function down(): void
    {
        Schema::table('return_detail', function (Blueprint $table): void {
            $table->dropColumn('return_detail_received_qty');
        });
    }
};
