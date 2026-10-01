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
        DB::table('member_level')
            ->where('member_level_code', 'AGT')
            ->update([
                'member_level_name' => 'Agen Utama',
                'member_level_description' => 'Level kemitraan Agen Utama',
            ]);

        DB::table('member_group')
            ->where('member_group_id', 2)
            ->update([
                'member_group_name' => 'Agen Utama',
                'member_group_description' => 'Akses portal Agen Utama',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('member_level')
            ->where('member_level_code', 'AGT')
            ->update([
                'member_level_name' => 'Agent',
                'member_level_description' => 'Level kemitraan Agent',
            ]);

        DB::table('member_group')
            ->where('member_group_id', 2)
            ->update([
                'member_group_name' => 'Agent',
                'member_group_description' => 'Akses portal agent',
            ]);
    }
};
