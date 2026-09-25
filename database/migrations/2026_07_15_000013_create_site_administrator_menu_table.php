<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: site_administrator_menu
 * Modul: Auth & Admin
 *
 * Konfigurasi menu dashboard backoffice admin.
 * Mendukung hierarki parent-child (self-referencing via `administrator_menu_par_id`)
 * untuk sub-menu bertingkat. Digunakan untuk merender sidebar secara dinamis
 * berdasarkan permission admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_administrator_menu', function (Blueprint $table) {
            $table->comment('Konfigurasi menu dashboard backoffice admin. Mendukung hierarki parent-child (self-referencing via `administrator_menu_par_id`) untuk sub-menu bertingkat. Digunakan untuk merender sidebar secara dinamis berdasarkan permission admin.');
            $table->increments('administrator_menu_id')->comment('ID Menu Administrator');
            $table->unsignedInteger('administrator_menu_par_id')->default(0)->comment('ID Menu Parent Administrator');
            $table->string('administrator_menu_title', 50)->default('')->comment('Judul Menu');
            $table->string('administrator_menu_description', 255)->default('')->comment('Deskripsi Menu');
            $table->string('administrator_menu_link', 255)->default('#')->comment('Link Menu');
            $table->string('administrator_menu_icon', 255)->default('')->comment('Ikon Menu');
            $table->string('administrator_menu_class', 255)->default('')->comment('Class CSS Menu');
            $table->unsignedInteger('administrator_menu_order_by')->default(0)->comment('Urutan Menu');
            $table->unsignedTinyInteger('administrator_menu_is_active')->default(1)->comment('Status Aktif');
            $table->longText('administrator_menu_action')->nullable()->comment('Menu Action (JSON)');

            $table->index('administrator_menu_par_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_administrator_menu');
    }
};
