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
            $sequences = [];
            $documents = [
                ['return', 'return_id', 'return_code', 'RTR'],
                ['goods_receive', 'goods_receive_id', 'goods_receive_number', 'GRN'],
                ['warehouse_stock_adjustment', 'stock_adjustment_id', 'stock_adjustment_code', 'ADJ'],
                ['member_stock_adjustment', 'stock_adjustment_id', 'stock_adjustment_code', 'ADJ'],
            ];

            foreach ($documents as [$table, $idColumn, $codeColumn, $prefix]) {
                $sequence = $sequences[$prefix] ?? 0;

                DB::table($table)
                    ->select([$idColumn, $codeColumn])
                    ->orderBy($idColumn)
                    ->chunkById(500, function ($rows) use (
                        &$sequence,
                        $table,
                        $idColumn,
                        $codeColumn,
                        $prefix,
                    ): void {
                        foreach ($rows as $row) {
                            $sequence++;
                            $storedCode = (string) $row->{$codeColumn};
                            $suffixSource = Str::startsWith($storedCode, 'DEV-')
                                ? $storedCode
                                : "{$table}|{$row->{$idColumn}}|{$storedCode}";
                            $code = implode('-', [
                                $prefix,
                                Str::padLeft((string) $sequence, 6, '0'),
                                Str::upper(Str::substr(hash('sha256', $suffixSource), 0, 6)),
                            ]);

                            DB::table($table)
                                ->where($idColumn, $row->{$idColumn})
                                ->update([$codeColumn => $code]);

                            if ($table === 'return') {
                                $this->updateReturnShippingReference((int) $row->{$idColumn}, $code);
                            }
                        }
                    }, $idColumn);

                $sequences[$prefix] = $sequence;
            }

            foreach ($sequences as $prefix => $sequence) {
                $this->updateSequenceConfig($prefix, $sequence);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new \RuntimeException(
            'Normalisasi kode dokumen internal tidak dapat dikembalikan secara otomatis.',
        );
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

    private function updateSequenceConfig(string $prefix, int $sequence): void
    {
        $now = now();
        $configKey = 'document_code.'.Str::lower($prefix).'_sequence';
        $config = DB::table('config')->where('config_key', $configKey);

        if ($config->exists()) {
            $config->update([
                'config_value' => (string) $sequence,
                'config_type' => 'integer',
                'config_updated_datetime' => $now,
            ]);

            return;
        }

        DB::table('config')->insert([
            'config_key' => $configKey,
            'config_value' => (string) $sequence,
            'config_type' => 'integer',
            'config_created_datetime' => $now,
            'config_updated_datetime' => $now,
        ]);
    }
};
