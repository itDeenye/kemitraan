<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: customer
 * Modul: Kemitraan
 *
 * Data pelanggan end-user milik mitra. Digunakan untuk pencatatan
 * penjualan ritel via fitur Sales/POS. Setiap mitra bisa memiliki
 * banyak customer. Alamat langsung disimpan di tabel ini (hanya 1 alamat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer', function (Blueprint $table) {
            $table->comment('Data pelanggan end-user milik mitra. Digunakan untuk pencatatan penjualan ritel. Alamat langsung inline di tabel ini (hanya 1 alamat).');
            $table->increments('customer_id')->comment('ID Customer');
            $table->unsignedInteger('customer_member_id')->comment('ID Mitra yang menginput customer ini');
            $table->string('customer_name', 200)->default('')->comment('Nama pelanggan');
            $table->string('customer_whatsapp', 20)->default('')->comment('Nomor WhatsApp');
            $table->string('customer_phone', 20)->default('')->comment('Nomor telepon lain');
            $table->string('customer_gender', 5)->default('')->comment('Jenis kelamin (L|P)');
            $table->date('customer_birth_date')->nullable()->comment('Tanggal lahir');

            // === Alamat (inline, hanya 1) ===
            $table->string('customer_address', 500)->default('')->comment('Alamat lengkap pelanggan');
            $table->unsignedInteger('customer_subdistrict_id')->default(0)->comment('ID Kelurahan');
            $table->unsignedInteger('customer_district_id')->default(0)->comment('ID Kecamatan');
            $table->unsignedInteger('customer_city_id')->default(0)->comment('ID Kota/Kabupaten');
            $table->unsignedInteger('customer_province_id')->default(0)->comment('ID Provinsi');

            $table->unsignedTinyInteger('customer_is_deleted')->default(0)->comment('Soft delete flag');
            $table->dateTime('customer_created_datetime')->comment('Tanggal input');

            $table->index('customer_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer');
    }
};
