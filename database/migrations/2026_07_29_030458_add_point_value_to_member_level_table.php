<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::whenTableDoesntHaveColumn(
            'member_level',
            'member_level_point_value',
            fn (Blueprint $table) => $table->unsignedInteger('member_level_point_value')
                ->default(0)
                ->after('member_level_min_order')
                ->comment('Nilai 1 Poin Reward Bulanan')
        );
    }

    public function down(): void
    {
        Schema::whenTableHasColumn(
            'member_level',
            'member_level_point_value',
            fn (Blueprint $table) => $table->dropColumn('member_level_point_value')
        );
    }
};
