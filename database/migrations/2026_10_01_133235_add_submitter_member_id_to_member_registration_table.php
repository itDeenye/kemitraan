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
        Schema::table('member_registration', function (Blueprint $table): void {
            $table->unsignedInteger('member_registration_submitter_member_id')
                ->default(0)
                ->after('member_registration_upline_member_id')
                ->comment('ID member yang mengajukan registrasi')
                ->index('idx_member_registration_submitter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_registration', function (Blueprint $table): void {
            $table->dropIndex('idx_member_registration_submitter');
            $table->dropColumn('member_registration_submitter_member_id');
        });
    }
};
