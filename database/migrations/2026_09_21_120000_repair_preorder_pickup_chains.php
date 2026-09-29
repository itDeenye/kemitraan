<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trx')
            || ! Schema::hasTable('shipping_courier_express')
            || ! Schema::hasTable('shipping_pickup')) {
            return;
        }

        DB::table('trx')
            ->where('trx_is_preorder', 1)
            ->where('trx_parent_trx_id', '>', 0)
            ->where('trx_seller_type', 'warehouse')
            ->where('trx_shipping_method', 'courier_express')
            ->whereIn('trx_status', [
                'waiting_stock_screening',
                'waiting_payment',
                'waiting_payment_approval',
                'processing',
            ])
            ->orderBy('trx_id')
            ->eachById(function (object $terminal): void {
                DB::transaction(function () use ($terminal): void {
                    $express = DB::table('shipping_courier_express')
                        ->where('shipping_courier_express_ref_type', 'trx')
                        ->where('shipping_courier_express_ref_id', $terminal->trx_id)
                        ->lockForUpdate()
                        ->first();
                    if (! $express
                        || filled($express->shipping_courier_express_order_id)
                        || filled($express->shipping_courier_express_pickup_number)
                        || filled($express->shipping_courier_express_awb)) {
                        return;
                    }

                    $chainIds = [(int) $terminal->trx_id];
                    $root = $terminal;
                    while ((int) $root->trx_parent_trx_id > 0) {
                        $root = DB::table('trx')
                            ->where('trx_id', $root->trx_parent_trx_id)
                            ->lockForUpdate()
                            ->first();
                        if (! $root || in_array((int) $root->trx_id, $chainIds, true)) {
                            return;
                        }
                        $chainIds[] = (int) $root->trx_id;
                    }

                    if ($root->trx_shipping_method !== 'pickup') {
                        return;
                    }

                    $rootPickup = DB::table('shipping_pickup')
                        ->where('shipping_pickup_ref_type', 'trx')
                        ->where('shipping_pickup_ref_id', $root->trx_id)
                        ->lockForUpdate()
                        ->first();
                    $warehouse = DB::table('warehouse')
                        ->where('warehouse_id', $terminal->trx_seller_id)
                        ->first();
                    if (! $rootPickup || blank($rootPickup->shipping_pickup_pin) || ! $warehouse) {
                        return;
                    }

                    DB::table('shipping_courier_express_status')
                        ->where(
                            'shipping_courier_express_status_shipping_courier_express_id',
                            $express->shipping_courier_express_id,
                        )
                        ->delete();
                    DB::table('shipping_detail')
                        ->where('shipping_detail_shipping_type', 'courier_express')
                        ->where('shipping_detail_shipping_id', $express->shipping_courier_express_id)
                        ->delete();
                    DB::table('shipping_courier_express')
                        ->where('shipping_courier_express_id', $express->shipping_courier_express_id)
                        ->delete();

                    $pickupId = DB::table('shipping_pickup')->insertGetId([
                        'shipping_pickup_ref_type' => 'trx',
                        'shipping_pickup_ref_id' => $terminal->trx_id,
                        'shipping_pickup_seller_address' => $warehouse->warehouse_address,
                        'shipping_pickup_seller_name' => mb_substr((string) $warehouse->warehouse_name, 0, 50),
                        'shipping_pickup_seller_mobilephone' => mb_substr((string) $warehouse->warehouse_phone, 0, 16),
                        'shipping_pickup_schedule_datetime' => null,
                        'shipping_pickup_pin' => $rootPickup->shipping_pickup_pin,
                        'shipping_pickup_delivery_note_number' => null,
                    ]);
                    DB::table('shipping_pickup_status')->insert([
                        'shipping_pickup_status_shipping_pickup_id' => $pickupId,
                        'shipping_pickup_status_ref_type' => 'trx',
                        'shipping_pickup_status_ref_id' => $terminal->trx_id,
                        'shipping_pickup_status_value' => 'pending',
                        'shipping_pickup_status_datetime' => now(),
                    ]);
                    DB::table('shipping_pickup')
                        ->where('shipping_pickup_ref_type', 'trx')
                        ->whereIn('shipping_pickup_ref_id', $chainIds)
                        ->update(['shipping_pickup_pin' => $rootPickup->shipping_pickup_pin]);
                    DB::table('trx')
                        ->where('trx_id', $terminal->trx_id)
                        ->update(['trx_shipping_method' => 'pickup']);
                });
            }, 100, 'trx_id');
    }

    public function down(): void
    {
        // Perbaikan data aktif tidak dikembalikan ke kondisi inkonsisten.
    }
};
