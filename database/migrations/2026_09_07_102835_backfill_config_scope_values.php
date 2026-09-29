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
        DB::table('config')
            ->whereIn('config_key', [
                'reward_monthly',
                'reward_stockist',
                'partnership',
            ])
            ->update(['config_scope' => 'commission']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('config')
            ->whereIn('config_key', [
                'reward_monthly',
                'reward_stockist',
                'partnership',
            ])
            ->update(['config_scope' => 'system']);
    }
};
