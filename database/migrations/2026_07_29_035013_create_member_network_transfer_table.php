<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_network_transfer', function (Blueprint $table) {
            $table->comment('Jadwal perpindahan parent member akibat upgrade, downgrade, atau koreksi jaringan.');
            $table->increments('member_network_transfer_id')->comment('ID perpindahan jaringan');
            $table->string('member_network_transfer_group_code', 50)->comment('Kode pengelompokan perpindahan yang diterapkan bersamaan');
            $table->unsignedInteger('member_network_transfer_upgrade_qualified_id')->default(0)->comment('ID approval upgrade jika perpindahan dipicu upgrade');
            $table->unsignedInteger('member_network_transfer_member_id')->comment('ID member yang dipindahkan');
            $table->unsignedInteger('member_network_transfer_from_parent_member_id')->default(0)->comment('ID parent sebelum perpindahan');
            $table->unsignedInteger('member_network_transfer_to_parent_member_id')->default(0)->comment('ID parent setelah perpindahan');
            $table->unsignedInteger('member_network_transfer_from_level_id')->default(0)->comment('ID level sebelum perpindahan jika disertai perubahan level');
            $table->unsignedInteger('member_network_transfer_to_level_id')->default(0)->comment('ID level setelah perpindahan jika disertai perubahan level');
            $table->enum('member_network_transfer_type', ['upgrade', 'downgrade', 'manual'])->comment('Penyebab perpindahan jaringan');
            $table->enum('member_network_transfer_status', ['scheduled', 'approved', 'rejected'])
                ->default('scheduled')
                ->comment('Status persetujuan perpindahan jaringan');
            $table->unsignedInteger('member_network_transfer_admin_id')->default(0)->comment('ID administrator yang memproses');
            $table->dateTime('member_network_transfer_approved_datetime')->nullable()->comment('Waktu perpindahan disetujui');
            $table->date('member_network_transfer_effective_date')->nullable()->comment('Tanggal perpindahan mulai berlaku');
            $table->dateTime('member_network_transfer_applied_datetime')->nullable()->comment('Waktu perpindahan benar-benar diterapkan');
            $table->string('member_network_transfer_note', 500)->default('')->comment('Catatan perpindahan jaringan');
            $table->dateTime('member_network_transfer_created_datetime')->comment('Waktu jadwal perpindahan dibuat');

            $table->unique(
                ['member_network_transfer_group_code', 'member_network_transfer_member_id'],
                'uq_member_network_transfer_group_member'
            );
            $table->index('member_network_transfer_upgrade_qualified_id', 'idx_member_network_transfer_qualified');
            $table->index(
                ['member_network_transfer_status', 'member_network_transfer_effective_date'],
                'idx_member_network_transfer_schedule'
            );
            $table->index('member_network_transfer_from_parent_member_id', 'idx_member_network_transfer_from_parent');
            $table->index('member_network_transfer_to_parent_member_id', 'idx_member_network_transfer_to_parent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_network_transfer');
    }
};
