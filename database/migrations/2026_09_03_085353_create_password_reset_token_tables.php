<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrator_password_reset_tokens', function (Blueprint $table): void {
            $table->comment('Token sekali pakai untuk reset password administrator.');
            $table->string('email', 100)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('member_password_reset_tokens', function (Blueprint $table): void {
            $table->comment('Token sekali pakai untuk reset password member.');
            $table->string('email', 100)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_password_reset_tokens');
        Schema::dropIfExists('administrator_password_reset_tokens');
    }
};
