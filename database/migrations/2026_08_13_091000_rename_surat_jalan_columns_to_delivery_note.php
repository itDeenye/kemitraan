<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'shipping_courier_express' => [
                'shipping_courier_express_surat_jalan',
                'shipping_courier_express_delivery_note_number',
            ],
            'shipping_courier_instant' => [
                'shipping_courier_instant_surat_jalan',
                'shipping_courier_instant_delivery_note_number',
            ],
            'shipping_courier_manual' => [
                'shipping_courier_manual_surat_jalan',
                'shipping_courier_manual_delivery_note_number',
            ],
            'shipping_pickup' => [
                'shipping_pickup_surat_jalan',
                'shipping_pickup_delivery_note_number',
            ],
            'goods_receive' => [
                'goods_receive_surat_jalan',
                'goods_receive_delivery_note_number',
            ],
        ] as $tableName => [$oldColumn, $newColumn]) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $oldColumn)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($oldColumn, $newColumn): void {
                $table->renameColumn($oldColumn, $newColumn);
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'shipping_courier_express' => [
                'shipping_courier_express_delivery_note_number',
                'shipping_courier_express_surat_jalan',
            ],
            'shipping_courier_instant' => [
                'shipping_courier_instant_delivery_note_number',
                'shipping_courier_instant_surat_jalan',
            ],
            'shipping_courier_manual' => [
                'shipping_courier_manual_delivery_note_number',
                'shipping_courier_manual_surat_jalan',
            ],
            'shipping_pickup' => [
                'shipping_pickup_delivery_note_number',
                'shipping_pickup_surat_jalan',
            ],
            'goods_receive' => [
                'goods_receive_delivery_note_number',
                'goods_receive_surat_jalan',
            ],
        ] as $tableName => [$oldColumn, $newColumn]) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $oldColumn)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($oldColumn, $newColumn): void {
                $table->renameColumn($oldColumn, $newColumn);
            });
        }
    }
};
