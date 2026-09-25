<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trx', function (Blueprint $table): void {
            $table->unsignedInteger('trx_voucher_id')
                ->default(0)
                ->after('trx_discount_value')
                ->comment('ID voucher reward stockist yang digunakan, 0 jika tanpa voucher');
            $table->unsignedInteger('trx_voucher_value')
                ->default(0)
                ->after('trx_voucher_id')
                ->comment('Nominal voucher yang digunakan pada transaksi');

            $table->index('trx_voucher_id');
        });
    }

    public function down(): void
    {
        Schema::table('trx', function (Blueprint $table): void {
            $table->dropIndex(['trx_voucher_id']);
            $table->dropColumn(['trx_voucher_id', 'trx_voucher_value']);
        });
    }
};
