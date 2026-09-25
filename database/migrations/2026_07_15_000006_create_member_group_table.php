<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: member_group
 * Modul: Kemitraan (Auth & Privilege)
 *
 * Mengelola grup/role akses dinamis untuk portal member.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_group', function (Blueprint $table) {
            $table->comment('Mengelola grup/role akses dinamis untuk portal member.');
            $table->increments('member_group_id')->comment('ID Group/Role Member');
            $table->string('member_group_name', 50)->comment('Nama Group/Role Member');
            $table->text('member_group_description')->nullable()->comment('Deskripsi Group/Role');
            $table->unsignedTinyInteger('member_group_is_active')->default(1)->comment('Status aktif (1:aktif, 0:nonaktif)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_group');
    }
};
