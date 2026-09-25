<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: site_administrator_group
 * Modul: Auth & Admin
 *
 * Master role/grup administrator. Membedakan peran admin
 * (superuser, administrator, finance, warehouse, CS) untuk kontrol
 * akses ke menu dan fitur backoffice. Diacu oleh `site_administrator`
 * dan `site_administrator_privilege`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_administrator_group', function (Blueprint $table) {
            $table->comment('Master role/grup administrator. Membedakan peran admin (superuser, administrator, finance, warehouse, CS) untuk kontrol akses ke menu dan fitur backoffice. Diacu oleh `site_administrator` dan `site_administrator_privilege`.');
            $table->increments('administrator_group_id')->comment('ID Grup Administrator');
            $table->string('administrator_group_title', 50)->nullable()->comment('Nama Grup Administrator');
            $table->enum('administrator_group_type', ['superuser', 'administrator'])
                ->default('administrator')
                ->comment('Tipe Grup Administrator');
            $table->unsignedTinyInteger('administrator_group_is_active')->default(1)->comment('Status Aktif');
            $table->longText('administrator_group_menu_array')->nullable()->comment('Menu Grup Administrator (JSON)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_administrator_group');
    }
};
