<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: member_privilege
 * Modul: Kemitraan (Auth & Privilege)
 *
 * Pivot table M:N menghubungkan `member_group` dan `member_menu`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_privilege', function (Blueprint $table) {
            $table->comment('Pivot table M:N menghubungkan member_group dan member_menu.');
            $table->unsignedInteger('member_privilege_member_group_id')->comment('ID Group Member');
            $table->unsignedInteger('member_privilege_member_menu_id')->comment('ID Menu Member');

            $table->primary(['member_privilege_member_group_id', 'member_privilege_member_menu_id'], 'pk_member_privilege');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_privilege');
    }
};
