<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_account', function (Blueprint $table) {
            $table->dropIndex(['member_account_member_id']);
            $table->unique('member_account_member_id');
            $table->unsignedTinyInteger('member_account_failed_login_attempts')
                ->default(0)
                ->after('member_account_last_login_datetime');
            $table->dateTime('member_account_last_failed_login_datetime')
                ->nullable()
                ->after('member_account_failed_login_attempts');
            $table->dateTime('member_account_locked_until')
                ->nullable()
                ->after('member_account_last_failed_login_datetime');
        });
    }

    public function down(): void
    {
        Schema::table('member_account', function (Blueprint $table) {
            $table->dropUnique(['member_account_member_id']);
            $table->index('member_account_member_id');
            $table->dropColumn([
                'member_account_failed_login_attempts',
                'member_account_last_failed_login_datetime',
                'member_account_locked_until',
            ]);
        });
    }
};
