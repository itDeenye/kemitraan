<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: member
 * Modul: Kemitraan
 *
 * Tabel inti profil member/mitra DNY. Menyimpan biodata, pohon jaringan
 * (upline/grouping via self-ref `member_upline_member_id`), status keanggotaan,
 * alamat domisili (FK ke ref_wilayah), data bank, dan identitas.
 *
 * Tabel ini adalah pusat relasi: diacu oleh hampir seluruh modul
 * (transaksi, komisi, stok, retur, voucher, audit, dll).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member', function (Blueprint $table) {
            $table->comment('Tabel inti profil member/mitra DNY. Menyimpan biodata, pohon jaringan, status keanggotaan, alamat domisili, data bank, dan identitas. Pusat relasi yang diacu oleh hampir seluruh modul.');
            $table->increments('member_id')->comment('ID unik member');
            $table->string('member_code', 30)->unique()->comment('Unique ID format aaaa/bbbb/cccc atau ST-aaaa/bbbb/cccc');
            $table->enum('member_level', ['reseller', 'agent', 'distributor'])->comment('Level Kemitraan (reseller, agent, distributor)');
            $table->unsignedInteger('member_parent_member_id')->default(0)->comment('ID Upline / Sponsor langsung');
            $table->unsignedInteger('member_agent_member_id')->default(0)->comment('ID Member Upline Level Agent');
            $table->unsignedInteger('member_distributor_member_id')->default(0)->comment('ID Member Upline Level Distributor');
            $table->unsignedInteger('member_stockist_id')->nullable()->comment('ID Stokis (jika member adalah stokis)');

            $table->index('member_parent_member_id');
            $table->index('member_agent_member_id', 'idx_member_agent_id');
            $table->index('member_distributor_member_id', 'idx_member_distributor_id');

            // === Biodata ===
            $table->string('member_name', 100)->default('')->comment('Nama Member');
            $table->string('member_email', 100)->default('')->comment('Email Member');
            $table->string('member_mobilephone', 16)->default('')->comment('Nomor Handphone Member');
            $table->enum('member_gender', ['Laki-laki', 'Perempuan'])->default('Laki-laki')->comment('Jenis Kelamin Member');
            $table->date('member_birth_date')->nullable()->comment('Tanggal Lahir Member');

            // === Alamat & Wilayah ===
            $table->string('member_address', 255)->nullable()->comment('Alamat Member');
            $table->unsignedInteger('member_subdistrict_id')->default(0)->comment('Kelurahan Member');
            $table->unsignedInteger('member_district_id')->default(0)->comment('Kecamatan Member');
            $table->unsignedInteger('member_city_id')->default(0)->comment('Kota/Kabupaten Member');
            $table->unsignedInteger('member_province_id')->default(0)->comment('Propinsi Member');
            $table->unsignedInteger('member_country_id')->default(0)->comment('Negara Member');

            // === Bank ===
            $table->unsignedInteger('member_bank_id')->default(0)->comment('ID Ref Bank');
            $table->string('member_bank_name', 100)->nullable()->comment('Nama Bank Member');
            $table->string('member_bank_account_name', 50)->nullable()->comment('Nama Rekening Member');
            $table->string('member_bank_account_no', 50)->nullable()->comment('Nomor Rekening Member');
            $table->string('member_bank_city', 50)->nullable()->comment('Kota Bank Member');
            $table->string('member_bank_branch', 50)->nullable()->comment('Cabang Bank Member');

            // === Identitas ===
            $table->enum('member_identity_type', ['KTP', 'SIM', 'PASPOR'])->default('KTP')->comment('Jenis Identitas Member');
            $table->char('member_identity_no', 20)->default('')->comment('Nomor Identitas Member');
            $table->string('member_identity_image', 255)->nullable()->comment('Gambar Identitas Member');
            $table->string('member_identity_image_filename', 255)->default('')->comment('Nama file gambar identitas');
            $table->string('member_nib', 20)->nullable()->comment('Nomor Induk Berusaha (NIB)');

            // === Foto & Sosial Media ===
            $table->string('member_image', 255)->nullable()->comment('Foto Profil Member');
            $table->string('member_image_filename', 255)->nullable()->comment('Nama file foto profil member');
            $table->string('member_instagram', 100)->nullable()->comment('Instagram Member');
            $table->string('member_facebook', 100)->nullable()->comment('Facebook Member');
            $table->string('member_tiktok', 100)->nullable()->comment('Tiktok Member');

            // === Status & Timestamp ===
            $table->dateTime('member_join_datetime')->comment('Tanggal Join');
            $table->unsignedTinyInteger('member_status')->default(1)->comment('0:non active | 1:active | 2:suspend | 3:deleted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member');
    }
};
