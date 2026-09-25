<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_point_transaction', function (Blueprint $table): void {
            $table->comment('Ledger poin member yang dicatat sekali saat pembayaran level terakhir disetujui.');
            $table->increments('member_point_transaction_id');
            $table->unsignedInteger('member_point_transaction_member_id');
            $table->unsignedInteger('member_point_transaction_trx_id');
            $table->unsignedInteger('member_point_transaction_quantity')->default(0);
            $table->unsignedSmallInteger('member_point_transaction_year');
            $table->unsignedTinyInteger('member_point_transaction_month');
            $table->dateTime('member_point_transaction_approved_datetime');

            $table->unique('member_point_transaction_trx_id', 'uq_member_point_transaction_trx');
            $table->index(
                ['member_point_transaction_member_id', 'member_point_transaction_year', 'member_point_transaction_month'],
                'idx_member_point_transaction_period'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_point_transaction');
    }
};
