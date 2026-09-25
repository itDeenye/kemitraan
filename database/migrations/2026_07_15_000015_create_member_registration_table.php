<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: member_registration
 * Modul: Kemitraan
 *
 * Pencatatan pengajuan registrasi kemitraan baru sebelum disetujui (approve)
 * oleh admin pusat. Setelah disetujui, data akan di-insert ke tabel member
 * dan member_account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_registration', function (Blueprint $table) {
            $table->comment('Pencatatan pengajuan registrasi kemitraan baru sebelum disetujui (approve) oleh admin pusat. Setelah disetujui, data akan di-insert ke tabel member dan member_account.');
            $table->increments('member_registration_id')->comment('ID Registrasi');
            $table->enum('member_registration_member_level', ['reseller', 'agent', 'distributor'])->comment('Level Kemitraan yang diajukan (reseller, agent, distributor)');
            $table->unsignedInteger('member_registration_parent_member_id')->default(0)->comment('ID Upline / Sponsor langsung');
            $table->unsignedInteger('member_registration_agent_member_id')->default(0)->comment('ID Member Upline Level Agent');
            $table->unsignedInteger('member_registration_distributor_member_id')->default(0)->comment('ID Member Upline Level Distributor');

            $table->index('member_registration_parent_member_id', 'idx_reg_parent_id');
            $table->index('member_registration_agent_member_id', 'idx_reg_agent_id');
            $table->index('member_registration_distributor_member_id', 'idx_reg_distributor_id');

            // === Biodata ===
            $table->string('member_registration_name', 100)->default('')->comment('Nama Lengkap');
            $table->string('member_registration_email', 100)->default('')->comment('Email');
            $table->string('member_registration_mobilephone', 16)->default('')->comment('Nomor Handphone/WhatsApp');
            $table->enum('member_registration_gender', ['Laki-laki', 'Perempuan'])->default('Laki-laki')->comment('Jenis Kelamin');
            $table->date('member_registration_birth_date')->nullable()->comment('Tanggal Lahir');

            // === Alamat ===
            $table->string('member_registration_address', 255)->nullable()->comment('Alamat Domisili');
            $table->unsignedInteger('member_registration_subdistrict_id')->default(0)->comment('Kelurahan');
            $table->unsignedInteger('member_registration_district_id')->default(0)->comment('Kecamatan');
            $table->unsignedInteger('member_registration_city_id')->default(0)->comment('Kota/Kabupaten');
            $table->unsignedInteger('member_registration_province_id')->default(0)->comment('Provinsi');
            $table->unsignedInteger('member_registration_country_id')->default(0)->comment('Negara');

            // === Bank ===
            $table->unsignedInteger('member_registration_bank_id')->default(0)->comment('ID Ref Bank');
            $table->string('member_registration_bank_name', 100)->nullable()->comment('Nama Bank');
            $table->string('member_registration_bank_account_name', 50)->nullable()->comment('Nama Pemilik Rekening');
            $table->string('member_registration_bank_account_no', 50)->nullable()->comment('Nomor Rekening');
            $table->string('member_registration_bank_city', 50)->nullable()->comment('Kota Bank');
            $table->string('member_registration_bank_branch', 50)->nullable()->comment('Cabang Bank');

            // === Identitas ===
            $table->enum('member_registration_identity_type', ['KTP', 'SIM', 'PASPOR'])->default('KTP')->comment('Jenis Identitas');
            $table->char('member_registration_identity_no', 20)->default('')->comment('Nomor Identitas');
            $table->string('member_registration_identity_image', 255)->nullable()->comment('File scan/foto kartu identitas (max 5MB)');
            $table->string('member_registration_identity_image_filename', 255)->default('')->comment('Nama file gambar identitas');
            $table->string('member_registration_nib', 20)->nullable()->comment('Nomor Induk Berusaha (NIB)');

            // === Akun ===
            $table->string('member_registration_username', 50)->comment('Username akun setelah approval (kode mitra)');
            $table->string('member_registration_password', 255)->comment('Bcrypt Password');

            // === Status & Verifikasi ===
            $table->enum('member_registration_status', ['requested', 'approved', 'rejected'])->default('requested')->comment('Status pengajuan');
            $table->unsignedInteger('member_registration_status_administrator_id')->default(0)->comment('ID Admin yang memproses');
            $table->dateTime('member_registration_status_datetime')->nullable()->comment('Waktu pemrosesan');
            $table->text('member_registration_note')->nullable()->comment('Catatan verifikasi (alasan disetujui/ditolak)');
            $table->dateTime('member_registration_datetime')->comment('Waktu pengajuan didaftarkan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_registration');
    }
};
