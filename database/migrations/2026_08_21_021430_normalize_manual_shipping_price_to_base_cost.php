<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('shipping_courier_manual')
            ->select([
                'shipping_courier_manual_id',
                'shipping_courier_manual_price',
                'shipping_courier_manual_insurance',
            ])
            ->where('shipping_courier_manual_insurance', '>', 0)
            ->orderBy('shipping_courier_manual_id')
            ->lazyById(500, 'shipping_courier_manual_id')
            ->each(function (object $shipping): void {
                DB::table('shipping_courier_manual')
                    ->where('shipping_courier_manual_id', $shipping->shipping_courier_manual_id)
                    ->update([
                        'shipping_courier_manual_price' => max(
                            0,
                            (int) $shipping->shipping_courier_manual_price
                                - (int) $shipping->shipping_courier_manual_insurance,
                        ),
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('shipping_courier_manual')
            ->select([
                'shipping_courier_manual_id',
                'shipping_courier_manual_price',
                'shipping_courier_manual_insurance',
            ])
            ->where('shipping_courier_manual_insurance', '>', 0)
            ->orderBy('shipping_courier_manual_id')
            ->lazyById(500, 'shipping_courier_manual_id')
            ->each(function (object $shipping): void {
                DB::table('shipping_courier_manual')
                    ->where('shipping_courier_manual_id', $shipping->shipping_courier_manual_id)
                    ->update([
                        'shipping_courier_manual_price' => (int) $shipping->shipping_courier_manual_price
                            + (int) $shipping->shipping_courier_manual_insurance,
                    ]);
            });
    }
};
