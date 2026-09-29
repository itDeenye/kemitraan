<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_courier_express', function (Blueprint $table) {
            $table->enum('shipping_courier_express_ref_type', [
                'trx',
                'return',
                'return_company',
                'return_replacement',
            ])->comment('Tipe referensi pengiriman express')->change();
        });
        Schema::table('shipping_courier_express_status', function (Blueprint $table) {
            $table->enum('shipping_courier_express_status_ref_type', [
                'trx',
                'return',
                'return_company',
                'return_replacement',
            ])->comment('Tipe referensi status pengiriman express')->change();
        });

        DB::table('shipping_courier_express')
            ->where('shipping_courier_express_ref_type', 'return')
            ->where('shipping_courier_express_leg', 'replacement_to_member')
            ->update(['shipping_courier_express_ref_type' => 'return_replacement']);
        DB::table('shipping_courier_express')
            ->where('shipping_courier_express_ref_type', 'return')
            ->update(['shipping_courier_express_ref_type' => 'return_company']);

        DB::table('shipping_courier_express_status')
            ->where('shipping_courier_express_status_ref_type', 'return')
            ->orderBy('shipping_courier_express_status_id')
            ->each(function (object $status): void {
                $referenceType = DB::table('shipping_courier_express')
                    ->where(
                        'shipping_courier_express_id',
                        $status->shipping_courier_express_status_shipping_courier_express_id,
                    )
                    ->value('shipping_courier_express_ref_type');

                DB::table('shipping_courier_express_status')
                    ->where('shipping_courier_express_status_id', $status->shipping_courier_express_status_id)
                    ->update([
                        'shipping_courier_express_status_ref_type' => $referenceType === 'return_replacement'
                            ? 'return_replacement'
                            : 'return_company',
                    ]);
            });

        Schema::table('shipping_courier_express', function (Blueprint $table) {
            $table->dropIndex('idx_express_return_leg');
            $table->dropColumn('shipping_courier_express_leg');
            $table->enum('shipping_courier_express_ref_type', [
                'trx',
                'return_company',
                'return_replacement',
            ])->comment('Tipe referensi: transaksi, retur ke Company, atau barang pengganti')->change();
        });
        Schema::table('shipping_courier_express_status', function (Blueprint $table) {
            $table->enum('shipping_courier_express_status_ref_type', [
                'trx',
                'return_company',
                'return_replacement',
            ])->comment('Tipe referensi: transaksi, retur ke Company, atau barang pengganti')->change();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Pemisahan tipe pengiriman retur tidak dapat dikembalikan otomatis.');
    }
};
