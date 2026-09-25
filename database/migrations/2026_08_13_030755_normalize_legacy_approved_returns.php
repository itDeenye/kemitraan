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
            ->where('return_status', 'approved')
            ->orderBy('return_id')
            ->chunkById(100, function ($returns): void {
                foreach ($returns as $return) {
                    $hasShipping = $this->hasReturnCompanyShipping((int) $return->return_id);
                    $status = $hasShipping ? 'waiting_member_shipment' : 'submitted';
                    $attributes = ['return_status' => $status];
                    if ($hasShipping) {
                        $this->reserveMemberStock(
                            (int) $return->return_id,
                            (int) $return->return_member_id,
                        );
                        $attributes['return_approved_datetime'] = $return->return_approved_datetime
                            ?? $return->return_created_datetime;
                    } else {
                        $attributes += [
                            'return_shipping_method' => null,
                            'return_shipping_cost' => 0,
                            'return_approved_by' => 0,
                            'return_approved_datetime' => null,
                        ];
                    }

                    DB::table('return')
                        ->where('return_id', $return->return_id)
                        ->update($attributes);
                    DB::table('return_status_log')->insert([
                        'return_status_log_return_id' => $return->return_id,
                        'return_status_log_status' => $status,
                        'return_status_log_note' => $hasShipping
                            ? 'Status retur lama dinormalisasi ke tahap menunggu pengiriman member.'
                            : 'Retur lama tanpa data pengiriman dikembalikan untuk ditinjau admin.',
                        'return_status_log_created_by' => 0,
                        'return_status_log_created_datetime' => now(),
                    ]);
                }
            }, 'return_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Normalisasi status retur lama tidak dapat dikembalikan otomatis.');
    }

    private function hasReturnCompanyShipping(int $returnId): bool
    {
        foreach ([
            'shipping_courier_express' => 'shipping_courier_express',
            'shipping_courier_instant' => 'shipping_courier_instant',
            'shipping_courier_manual' => 'shipping_courier_manual',
            'shipping_pickup' => 'shipping_pickup',
        ] as $table => $prefix) {
            if (DB::table($table)
                ->where("{$prefix}_ref_type", 'return_company')
                ->where("{$prefix}_ref_id", $returnId)
                ->exists()) {
                return true;
            }
        }

        return false;
    }

    private function reserveMemberStock(int $returnId, int $memberId): void
    {
        $quantities = DB::table('return_detail')
            ->where('return_detail_return_id', $returnId)
            ->groupBy('return_detail_product_id')
            ->select('return_detail_product_id')
            ->selectRaw('SUM(return_detail_qty) AS quantity')
            ->get();

        foreach ($quantities as $item) {
            DB::table('member_stock')
                ->where('member_stock_member_id', $memberId)
                ->where('member_stock_product_id', $item->return_detail_product_id)
                ->increment('member_stock_transfer_out', (int) $item->quantity);
        }
    }
};
