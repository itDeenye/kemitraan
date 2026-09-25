<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('reward_share_profit', 'trx_spread_payment');

        Schema::table('trx_spread_payment', function (Blueprint $table) {
            $table->renameColumn('reward_share_profit_id', 'trx_spread_payment_id');
            $table->renameColumn('reward_share_profit_trx_id', 'trx_spread_payment_trx_id');
            $table->renameColumn('reward_share_profit_recipient_type', 'trx_spread_payment_recipient_type');
            $table->renameColumn('reward_share_profit_recipient_id', 'trx_spread_payment_recipient_id');
            $table->renameColumn('reward_share_profit_bank_id', 'trx_spread_payment_bank_id');
            $table->renameColumn('reward_share_profit_account_name', 'trx_spread_payment_account_name');
            $table->renameColumn('reward_share_profit_account_number', 'trx_spread_payment_account_number');
            $table->renameColumn('reward_share_profit_percentage', 'trx_spread_payment_percentage');
            $table->renameColumn('reward_share_profit_amount', 'trx_spread_payment_amount');
            $table->renameColumn('reward_share_profit_status', 'trx_spread_payment_status');
            $table->renameColumn('reward_share_profit_verified_by', 'trx_spread_payment_verified_by');
            $table->renameColumn('reward_share_profit_verified_datetime', 'trx_spread_payment_verified_datetime');
            $table->renameColumn('reward_share_profit_note', 'trx_spread_payment_note');
            $table->renameColumn('reward_share_profit_created_datetime', 'trx_spread_payment_created_datetime');

            $table->renameIndex(
                'reward_share_profit_reward_share_profit_trx_id_index',
                'trx_spread_payment_trx_id_index',
            );
            $table->renameIndex(
                'reward_share_profit_trx_status_index',
                'trx_spread_payment_trx_status_index',
            );
            $table->renameIndex(
                'reward_share_profit_recipient_index',
                'trx_spread_payment_recipient_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx_spread_payment', function (Blueprint $table) {
            $table->renameIndex(
                'trx_spread_payment_trx_id_index',
                'reward_share_profit_reward_share_profit_trx_id_index',
            );
            $table->renameIndex(
                'trx_spread_payment_trx_status_index',
                'reward_share_profit_trx_status_index',
            );
            $table->renameIndex(
                'trx_spread_payment_recipient_index',
                'reward_share_profit_recipient_index',
            );

            $table->renameColumn('trx_spread_payment_id', 'reward_share_profit_id');
            $table->renameColumn('trx_spread_payment_trx_id', 'reward_share_profit_trx_id');
            $table->renameColumn('trx_spread_payment_recipient_type', 'reward_share_profit_recipient_type');
            $table->renameColumn('trx_spread_payment_recipient_id', 'reward_share_profit_recipient_id');
            $table->renameColumn('trx_spread_payment_bank_id', 'reward_share_profit_bank_id');
            $table->renameColumn('trx_spread_payment_account_name', 'reward_share_profit_account_name');
            $table->renameColumn('trx_spread_payment_account_number', 'reward_share_profit_account_number');
            $table->renameColumn('trx_spread_payment_percentage', 'reward_share_profit_percentage');
            $table->renameColumn('trx_spread_payment_amount', 'reward_share_profit_amount');
            $table->renameColumn('trx_spread_payment_status', 'reward_share_profit_status');
            $table->renameColumn('trx_spread_payment_verified_by', 'reward_share_profit_verified_by');
            $table->renameColumn('trx_spread_payment_verified_datetime', 'reward_share_profit_verified_datetime');
            $table->renameColumn('trx_spread_payment_note', 'reward_share_profit_note');
            $table->renameColumn('trx_spread_payment_created_datetime', 'reward_share_profit_created_datetime');
        });

        Schema::rename('trx_spread_payment', 'reward_share_profit');
    }
};
