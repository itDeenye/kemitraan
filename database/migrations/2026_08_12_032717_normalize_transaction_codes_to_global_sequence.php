<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('trx', function (Blueprint $table) {
                $table->string('trx_code', 50)
                    ->comment('Kode transaksi global: TRX-{penjual}-{pembeli}-{urutan}-{unik}')
                    ->change();
            });
            Schema::table('shipping_courier_express_status', function (Blueprint $table) {
                $table->string('shipping_courier_express_status_ref_code', 50)
                    ->default('')
                    ->comment('Kode referensi status')
                    ->change();
            });
            Schema::table('shipping_courier_instant_status', function (Blueprint $table) {
                $table->string('shipping_courier_instant_status_ref_code', 50)
                    ->default('')
                    ->comment('Kode referensi status')
                    ->change();
            });
        }

        DB::transaction(function (): void {
            $sequence = 0;

            DB::table('trx')
                ->select(['trx_id', 'trx_code', 'trx_seller_type', 'trx_buyer_type'])
                ->orderBy('trx_id')
                ->chunkById(500, function ($transactions) use (&$sequence): void {
                    foreach ($transactions as $transaction) {
                        $sequence++;
                        $storedCode = (string) $transaction->trx_code;
                        $developmentKey = match ($storedCode) {
                            'DEV-SALE-READY-TO-SH' => 'DEV-SALE-READY-TO-SHIP',
                            'DEV-SALE-EXPRESS-DON' => 'DEV-SALE-EXPRESS-DONE',
                            default => $storedCode,
                        };
                        $suffixSource = str_starts_with($storedCode, 'DEV-')
                            ? $developmentKey
                            : "{$transaction->trx_id}|{$transaction->trx_code}";
                        $code = implode('-', [
                            'TRX',
                            $this->partyCode((string) $transaction->trx_seller_type),
                            $this->partyCode((string) $transaction->trx_buyer_type),
                            Str::padLeft((string) $sequence, 6, '0'),
                            Str::upper(substr(hash('sha256', $suffixSource), 0, 6)),
                        ]);

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

            $now = now();
            $sequenceConfig = DB::table('config')
                ->where('config_key', 'transaction.code_sequence');

            if ($sequenceConfig->exists()) {
                $sequenceConfig->update([
                    'config_value' => (string) $sequence,
                    'config_type' => 'integer',
                    'config_updated_datetime' => $now,
                ]);
            } else {
                DB::table('config')->insert([
                    'config_key' => 'transaction.code_sequence',
                    'config_value' => (string) $sequence,
                    'config_type' => 'integer',
                    'config_created_datetime' => $now,
                    'config_updated_datetime' => $now,
                ]);
            }
        });

        Schema::table('trx', function (Blueprint $table) {
            $table->dropIndex(['trx_code']);
            $table->unique('trx_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new \RuntimeException(
            'Normalisasi kode transaksi global tidak dapat dikembalikan secara otomatis.',
        );
    }

    private function partyCode(string $partyType): string
    {
        return match ($partyType) {
            'warehouse' => 'CMP',
            'distributor' => 'DST',
            'agent' => 'AGT',
            'reseller' => 'RSL',
            'customer' => 'CUS',
            default => 'UNK',
        };
    }
};
