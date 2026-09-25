<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'shipping_courier_express' => 'shipping_courier_express_delivery_note_number',
            'shipping_courier_instant' => 'shipping_courier_instant_delivery_note_number',
            'shipping_courier_manual' => 'shipping_courier_manual_delivery_note_number',
            'shipping_pickup' => 'shipping_pickup_delivery_note_number',
        ] as $tableName => $column) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, $column)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($column): void {
                $table->string($column, 50)
                    ->nullable()
                    ->comment('Nomor surat jalan pengiriman');
            });
        }

        Schema::create('shipping_detail', function (Blueprint $table): void {
            $table->comment('Batch produk per pengiriman. Satu surat jalan dapat memuat banyak batch produk.');
            $table->increments('shipping_detail_id');
            $table->enum('shipping_detail_shipping_type', [
                'courier_express',
                'courier_instant',
                'courier_manual',
                'pickup',
            ]);
            $table->unsignedInteger('shipping_detail_shipping_id');
            $table->unsignedInteger('shipping_detail_product_id');
            $table->string('shipping_detail_batch_number', 50)->comment('Nomor batch produk');
            $table->unsignedInteger('shipping_detail_qty')->default(0);
            $table->date('shipping_detail_expire_date')->nullable()->comment('Tanggal kedaluwarsa batch');
            $table->index(
                ['shipping_detail_shipping_type', 'shipping_detail_shipping_id'],
                'idx_shipping_detail_header'
            );
            $table->index('shipping_detail_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_detail');
        foreach ([
            'shipping_courier_express' => 'shipping_courier_express_delivery_note_number',
            'shipping_courier_instant' => 'shipping_courier_instant_delivery_note_number',
            'shipping_courier_manual' => 'shipping_courier_manual_delivery_note_number',
            'shipping_pickup' => 'shipping_pickup_delivery_note_number',
        ] as $tableName => $column) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, $column)) {
                Schema::table($tableName, function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
