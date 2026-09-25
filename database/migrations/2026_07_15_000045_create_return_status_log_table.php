<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: return_status_log
 * Modul: Retur
 *
 * Catatan log perubahan status pengajuan retur barang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_status_log', function (Blueprint $table) {
            $table->comment('Catatan log perubahan status pengajuan retur barang.');
            $table->increments('return_status_log_id')->comment('ID log status return');
            $table->unsignedInteger('return_status_log_return_id')->comment('ID return');
            $table->enum('return_status_log_status', ['submitted', 'under_review', 'approved', 'rejected', 'completed'])->comment('Status return');
            $table->string('return_status_log_note', 255)->nullable()->comment('Catatan/Keterangan log');
            $table->unsignedInteger('return_status_log_created_by')->default(0)->comment('ID Admin/Member pengubah status');
            $table->dateTime('return_status_log_created_datetime')->comment('Waktu log dibuat');

            $table->index('return_status_log_return_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_status_log');
    }
};
