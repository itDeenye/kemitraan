<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_refresh_token', function (Blueprint $table): void {
            $table->comment('Refresh token API v1 untuk sesi login administrator dan member.');
            $table->bigIncrements('refresh_token_id')->comment('ID refresh token');
            $table->enum('refresh_token_owner_type', ['admin', 'member'])->comment('Tipe pemilik refresh token');
            $table->unsignedInteger('refresh_token_owner_id')->comment('ID administrator atau akun member');
            $table->char('refresh_token_hash', 64)->unique()->comment('Hash SHA-256 refresh token');
            $table->string('refresh_token_device_name', 100)->default('')->comment('Nama perangkat sesi');
            $table->dateTime('refresh_token_expires_at')->comment('Batas waktu penggunaan refresh token');
            $table->dateTime('refresh_token_last_used_at')->nullable()->comment('Waktu refresh token terakhir digunakan');
            $table->dateTime('refresh_token_revoked_at')->nullable()->comment('Waktu refresh token dicabut');
            $table->dateTime('refresh_token_created_at')->comment('Waktu refresh token dibuat');

            $table->index(
                ['refresh_token_owner_type', 'refresh_token_owner_id'],
                'auth_refresh_token_owner_index',
            );
            $table->index('refresh_token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_refresh_token');
    }
};
