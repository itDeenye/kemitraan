<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trx_spread_payment')) {
            return;
        }

        foreach ([
            'trx_spread_payment_trx_id_index',
            'trx_spread_payment_trx_status_index',
            'trx_spread_payment_recipient_index',
        ] as $indexName) {
            if (Schema::hasIndex('trx_spread_payment', $indexName)) {
                Schema::table('trx_spread_payment', function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }
        }

        Schema::table('trx_spread_payment', function (Blueprint $table) {
            $table->index('trx_spread_payment_trx_id', 'trx_spread_payment_trx_id_index');
            $table->index(
                ['trx_spread_payment_trx_id', 'trx_spread_payment_status'],
                'trx_spread_payment_trx_status_index'
            );
            $table->index(
                ['trx_spread_payment_recipient_type', 'trx_spread_payment_recipient_id'],
                'trx_spread_payment_recipient_index'
            );
        });
    }

    public function down(): void {}
};
