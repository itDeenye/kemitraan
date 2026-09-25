<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->normalizeMemberCodes();
            $this->normalizeTransactionCodes();
            $this->normalizeDocumentCodes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new \RuntimeException(
            'Perubahan format kode internal tidak dapat dikembalikan secara otomatis.',
        );
    }

    private function normalizeMemberCodes(): void
    {
        DB::table('member')
            ->select(['member_id', 'member_code'])
            ->orderBy('member_id')
            ->chunkById(500, function ($members): void {
                foreach ($members as $member) {
                    DB::table('member')
                        ->where('member_id', $member->member_id)
                        ->update([
                            'member_code' => Str::replaceFirst(
                                'DNY-',
                                'DNY',
                                (string) $member->member_code,
                            ),
                        ]);
                }
            }, 'member_id');
    }

    private function normalizeTransactionCodes(): void
    {
        DB::table('trx')
            ->select(['trx_id', 'trx_code'])
            ->orderBy('trx_id')
            ->chunkById(500, function ($transactions): void {
                foreach ($transactions as $transaction) {
                    $code = Str::replace('-', '/', (string) $transaction->trx_code);
                    DB::table('trx')
                        ->where('trx_id', $transaction->trx_id)
                        ->update(['trx_code' => $code]);

                    DB::table('shipping_courier_express_status')
                        ->where('shipping_courier_express_status_ref_type', 'trx')
                        ->where('shipping_courier_express_status_ref_id', $transaction->trx_id)
                        ->update(['shipping_courier_express_status_ref_code' => $code]);
                    DB::table('shipping_courier_instant_status')
                        ->where('shipping_courier_instant_status_ref_type', 'trx')
                        ->where('shipping_courier_instant_status_ref_id', $transaction->trx_id)
                        ->update(['shipping_courier_instant_status_ref_code' => $code]);
                }
            }, 'trx_id');
    }

    private function normalizeDocumentCodes(): void
    {
        $documents = [
            ['return', 'return_id', 'return_code'],
            ['goods_receive', 'goods_receive_id', 'goods_receive_number'],
            ['warehouse_stock_adjustment', 'stock_adjustment_id', 'stock_adjustment_code'],
            ['member_stock_adjustment', 'stock_adjustment_id', 'stock_adjustment_code'],
        ];

        foreach ($documents as [$table, $idColumn, $codeColumn]) {
            DB::table($table)
                ->select([$idColumn, $codeColumn])
                ->orderBy($idColumn)
                ->chunkById(500, function ($rows) use (
                    $table,
                    $idColumn,
                    $codeColumn,
                ): void {
                    foreach ($rows as $row) {
                        $code = Str::replace('-', '/', (string) $row->{$codeColumn});
                        DB::table($table)
                            ->where($idColumn, $row->{$idColumn})
                            ->update([$codeColumn => $code]);

                        if ($table === 'return') {
                            $this->updateReturnShippingReference((int) $row->{$idColumn}, $code);
                        }
                    }
                }, $idColumn);
        }
    }

    private function updateReturnShippingReference(int $returnId, string $code): void
    {
        DB::table('shipping_courier_express_status')
            ->where('shipping_courier_express_status_ref_type', 'return')
            ->where('shipping_courier_express_status_ref_id', $returnId)
            ->update(['shipping_courier_express_status_ref_code' => $code]);
        DB::table('shipping_courier_instant_status')
            ->where('shipping_courier_instant_status_ref_type', 'return')
            ->where('shipping_courier_instant_status_ref_id', $returnId)
            ->update(['shipping_courier_instant_status_ref_code' => $code]);
    }
};
