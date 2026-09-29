<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('return', function (Blueprint $table) {
            $table->enum('return_replacement_shipping_method', [
                'courier_express',
                'courier_instant',
                'courier_manual',
                'pickup',
            ])->nullable()->comment('Metode pengiriman barang pengganti')->change();
        });

        foreach ($this->shippingTables() as $table => $prefix) {
            Schema::table($table, function (Blueprint $table) use ($prefix) {
                $table->enum("{$prefix}_ref_type", [
                    'trx',
                    'return',
                    'return_company',
                    'return_replacement',
                ])->comment('Tipe referensi pengiriman')->change();
            });

            Schema::table("{$table}_status", function (Blueprint $table) use ($prefix) {
                $table->enum("{$prefix}_status_ref_type", [
                    'trx',
                    'return',
                    'return_company',
                    'return_replacement',
                ])->comment('Tipe referensi status pengiriman')->change();
            });

            DB::table($table)
                ->where("{$prefix}_ref_type", 'return')
                ->update(["{$prefix}_ref_type" => 'return_company']);
            DB::table("{$table}_status")
                ->where("{$prefix}_status_ref_type", 'return')
                ->update(["{$prefix}_status_ref_type" => 'return_company']);

            Schema::table($table, function (Blueprint $table) use ($prefix) {
                $table->enum("{$prefix}_ref_type", [
                    'trx',
                    'return_company',
                    'return_replacement',
                ])->comment('Tipe referensi: transaksi, retur ke Company, atau barang pengganti')->change();
            });
            Schema::table("{$table}_status", function (Blueprint $table) use ($prefix) {
                $table->enum("{$prefix}_status_ref_type", [
                    'trx',
                    'return_company',
                    'return_replacement',
                ])->comment('Tipe referensi: transaksi, retur ke Company, atau barang pengganti')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Dukungan multi-metode pengiriman retur tidak dapat dikembalikan otomatis.');
    }

    /** @return array<string, string> */
    private function shippingTables(): array
    {
        return [
            'shipping_courier_instant' => 'shipping_courier_instant',
            'shipping_courier_manual' => 'shipping_courier_manual',
            'shipping_pickup' => 'shipping_pickup',
        ];
    }
};
