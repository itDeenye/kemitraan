<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('config')
            ->where('config_key', 'partnership.spread_payment_percentage')
            ->update(['config_value' => '1', 'config_updated_datetime' => now()]);
        if (Schema::hasTable('trx_spread_payment')) {
            Schema::table('trx_spread_payment', function (Blueprint $table): void {
                if (! Schema::hasColumn('trx_spread_payment', 'trx_spread_payment_receipt_file')) {
                    $table->string('trx_spread_payment_receipt_file', 255)
                        ->nullable()
                        ->after('trx_spread_payment_amount')
                        ->comment('Bukti transfer spread payment');
                }
                if (! Schema::hasColumn('trx_spread_payment', 'trx_spread_payment_transfer_datetime')) {
                    $table->dateTime('trx_spread_payment_transfer_datetime')
                        ->nullable()
                        ->after('trx_spread_payment_receipt_file')
                        ->comment('Waktu transfer spread payment');
                }
            });
        }

        if (Schema::hasTable('return') && Schema::hasColumn('return', 'return_code')) {
            Schema::table('return', function (Blueprint $table): void {
                $table->string('return_code', 30)
                    ->nullable()
                    ->comment('Nomor retur yang dibuat setelah retur disetujui')
                    ->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('return') && Schema::hasColumn('return', 'return_code')) {
            Schema::table('return', function (Blueprint $table): void {
                $table->string('return_code', 30)
                    ->nullable(false)
                    ->comment('Nomor retur')
                    ->change();
            });
        }

        if (Schema::hasTable('trx_spread_payment')) {
            Schema::table('trx_spread_payment', function (Blueprint $table): void {
                foreach (['trx_spread_payment_receipt_file', 'trx_spread_payment_transfer_datetime'] as $column) {
                    if (Schema::hasColumn('trx_spread_payment', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

    }
};
