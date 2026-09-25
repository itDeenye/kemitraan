<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->bigIncrements('media_id');
            $table->uuid('media_uuid')->unique();
            $table->string('media_uploader_type', 150);
            $table->unsignedBigInteger('media_uploader_id');
            $table->string('media_collection', 50);
            $table->string('media_original_name');
            $table->string('media_original_mime_type', 100);
            $table->unsignedBigInteger('media_original_size');
            $table->string('media_mime_type', 100)->nullable();
            $table->unsignedBigInteger('media_size')->nullable();
            $table->unsignedInteger('media_chunk_size');
            $table->unsignedInteger('media_total_chunks');
            $table->unsignedInteger('media_uploaded_chunks')->default(0);
            $table->enum('media_status', [
                'pending',
                'uploading',
                'processing',
                'ready',
                'failed',
                'aborted',
            ])->default('pending');
            $table->string('media_disk', 30);
            $table->string('media_path')->nullable();
            $table->char('media_checksum', 64)->nullable();
            $table->text('media_error_message')->nullable();
            $table->dateTime('media_expires_at')->index();
            $table->dateTime('media_created_datetime');
            $table->dateTime('media_updated_datetime');

            $table->index(
                ['media_uploader_type', 'media_uploader_id', 'media_status'],
                'idx_media_uploader_status'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
