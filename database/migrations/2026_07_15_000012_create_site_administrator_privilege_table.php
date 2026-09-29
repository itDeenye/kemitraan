<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: site_administrator_privilege
 * Modul: Auth & Admin
 *
 * Izin akses (permission) admin per menu. Menentukan action apa saja
 * yang boleh diakses oleh group role tertentu pada menu tertentu.
 * Relasi ke `site_administrator_group` dan `site_administrator_menu`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_administrator_privilege', function (Blueprint $table) {
            $table->comment('Izin akses (permission) admin per menu. Menentukan action apa saja yang boleh diakses oleh group role tertentu pada menu tertentu. Relasi ke `site_administrator_group` dan `site_administrator_menu`.');
            $table->increments('administrator_privilege_id')->comment('ID Privilege Administrator');
            $table->unsignedInteger('administrator_privilege_administrator_group_id')->comment('ID Grup Administrator');
            $table->unsignedInteger('administrator_privilege_administrator_menu_id')->comment('ID Menu Administrator');
            $table->longText('administrator_privilege_action')->nullable()->comment('Action Menu (JSON)');

            $table->index('administrator_privilege_administrator_group_id', 'idx_privilege_group_id');
            $table->index('administrator_privilege_administrator_menu_id', 'idx_privilege_menu_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_administrator_privilege');
    }
};
