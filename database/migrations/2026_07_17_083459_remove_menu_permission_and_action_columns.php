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
        if (Schema::hasColumn('member_menu', 'member_menu_permission')) {
            Schema::table('member_menu', function (Blueprint $table) {
                $table->dropColumn('member_menu_permission');
            });
        }

        if (Schema::hasColumn('site_administrator_menu', 'administrator_menu_action')) {
            Schema::table('site_administrator_menu', function (Blueprint $table) {
                $table->dropColumn('administrator_menu_action');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('member_menu', 'member_menu_permission')) {
            Schema::table('member_menu', function (Blueprint $table) {
                $table->string('member_menu_permission', 100)
                    ->default('')
                    ->after('member_menu_sort_order');
            });
        }

        if (! Schema::hasColumn('site_administrator_menu', 'administrator_menu_action')) {
            Schema::table('site_administrator_menu', function (Blueprint $table) {
                $table->longText('administrator_menu_action')
                    ->nullable()
                    ->after('administrator_menu_is_active');
            });
        }
    }
};
