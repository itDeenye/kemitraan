<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: member_menu
 * Modul: Kemitraan (Auth & Privilege)
 *
 * Mengelola struktur menu portal member secara dinamis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_menu', function (Blueprint $table) {
            $table->comment('Mengelola struktur menu portal member secara dinamis.');
            $table->increments('member_menu_id')->comment('ID Menu Member');
            $table->unsignedInteger('member_menu_parent_id')->default(0)->comment('Parent Menu ID untuk Sub-menu');
            $table->string('member_menu_name', 50)->comment('Nama Menu');
            $table->string('member_menu_route', 100)->default('')->comment('Route/URL Link Menu');
            $table->string('member_menu_icon', 50)->default('')->comment('Icon class (FontAwesome/Heroicons)');
            $table->unsignedSmallInteger('member_menu_sort_order')->default(0)->comment('Urutan tampil');
            $table->string('member_menu_permission', 100)->default('')->comment('Kode permission (contoh: stockist.view, distributor.order)');
            $table->unsignedTinyInteger('member_menu_is_active')->default(1)->comment('Status aktif (1:aktif, 0:nonaktif)');

            $table->index('member_menu_parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_menu');
    }
};
