<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_administrator', function (Blueprint $table) {
            $table->dropIndex(['administrator_username']);
            $table->unique('administrator_username');
            $table->unsignedTinyInteger('administrator_failed_login_attempts')
                ->default(0)
                ->after('administrator_last_login');
            $table->dateTime('administrator_last_failed_login_datetime')
                ->nullable()
                ->after('administrator_failed_login_attempts');
            $table->dateTime('administrator_locked_until')
                ->nullable()
                ->after('administrator_last_failed_login_datetime');
        });
    }

    public function down(): void
    {
        Schema::table('site_administrator', function (Blueprint $table) {
            $table->dropUnique(['administrator_username']);
            $table->index('administrator_username');
            $table->dropColumn([
                'administrator_failed_login_attempts',
                'administrator_last_failed_login_datetime',
                'administrator_locked_until',
            ]);
        });
    }
};
