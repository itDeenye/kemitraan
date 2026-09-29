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
        $developmentMemberCount = DB::table('member')
            ->whereIn('member_mobilephone', [
                '081200000101',
                '081200000102',
                '081200000201',
            ])
            ->count();

        if ($developmentMemberCount !== 3) {
            return;
        }

        DB::table('config')->insertOrIgnore([
            'config_key' => 'development.seeded_at',
            'config_value' => 'existing_development_dataset',
            'config_type' => 'string',
            'config_created_datetime' => now(),
            'config_updated_datetime' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('config')
            ->where('config_key', 'development.seeded_at')
            ->where('config_value', 'existing_development_dataset')
            ->delete();
    }
};
