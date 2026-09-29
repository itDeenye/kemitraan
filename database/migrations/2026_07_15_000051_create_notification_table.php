<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: notification
 * Modul: Notifikasi (Pusat & Mitra)
 *
 * Tabel tunggal untuk mengelola seluruh notifikasi sistem baik untuk Member (Mitra) maupun Admin (Backoffice).
 * Menggunakan sistem referensi polymorphic (_ref_table & _ref_id) untuk mengaitkan ke entitas pemicu (transaksi, stok, komisi, retur, dll).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification', function (Blueprint $table) {
            $table->comment('Tabel tunggal untuk notifikasi sistem baik Member maupun Admin dengan referensi polymorphic.');
            $table->increments('notification_id')->comment('ID Notifikasi');
            $table->enum('notification_user_type', ['member', 'admin'])->comment('Penerima notifikasi (member=Mitra, admin=Administrator)');
            $table->unsignedInteger('notification_user_id')->comment('ID User penerima (member_id atau administrator_id)');
            $table->string('notification_title', 150)->comment('Judul Notifikasi');
            $table->text('notification_content')->comment('Konten/Isi Detail Notifikasi');
            $table->string('notification_category', 50)->comment('Kategori (system|stock|trx|bonus|reward|return)');
            
            // Referensi Polymorphic
            $table->string('notification_ref_table', 50)->nullable()->comment('Nama tabel referensi pemicu (misal: trx, warehouse_stock, return)');
            $table->unsignedInteger('notification_ref_id')->nullable()->comment('ID baris tabel referensi pemicu');
            
            $table->unsignedTinyInteger('notification_is_read')->default(0)->comment('Status dibaca (1=Sudah, 0=Belum)');
            $table->dateTime('notification_read_datetime')->nullable()->comment('Waktu notifikasi dibaca');
            $table->dateTime('notification_created_datetime')->nullable()->comment('Waktu notifikasi dibuat');

            $table->index(['notification_user_type', 'notification_user_id'], 'idx_notif_user');
            $table->index(['notification_ref_table', 'notification_ref_id'], 'idx_notif_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification');
    }
};
