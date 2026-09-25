<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: audittrail
 * Modul: Audit & Konfigurasi
 *
 * Audit log aktivitas administrator backoffice.
 * Mencatat setiap aksi admin (create, update, delete) beserta
 * menu, deskripsi, IP address, dan user agent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audittrail', function (Blueprint $table) {
            $table->comment('Audit log aktivitas administrator backoffice. Mencatat setiap aksi admin (create, update, delete) beserta menu, deskripsi, IP address, dan user agent.');
            $table->increments('audittrail_id')->comment('ID unik log audit');
            $table->integer('audittrail_admin_id')->default(0)->comment('ID administrator yang melakukan aksi');
            $table->string('audittrail_admin_name', 200)->comment('Nama admin');
            $table->string('audittrail_menu_name', 200)->comment('Nama menu');
            $table->string('audittrail_desc', 300)->comment('Keterangan');
            $table->string('audittrail_act', 100)->nullable()->comment('Aksi / Metode API yang dilakukan');
            $table->longText('audittrail_payload')->nullable()->comment('Data input / request payload');
            $table->longText('audittrail_results')->nullable()->comment('Hasil response / response data');
            $table->string('audittrail_ip_address', 20)->comment('IP address');
            $table->text('audittrail_user_agent')->comment('User agent');
            $table->dateTime('audittrail_datetime')->nullable()->comment('Waktu event');

            $table->index('audittrail_admin_id');
            $table->index('audittrail_admin_name');
            $table->index('audittrail_datetime');
            $table->index('audittrail_desc');
            $table->index('audittrail_menu_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audittrail');
    }
};
