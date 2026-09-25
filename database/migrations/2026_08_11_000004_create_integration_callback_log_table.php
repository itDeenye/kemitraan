<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_callback_log', function (Blueprint $table): void {
            $table->comment('Audit callback yang diterima dari sistem eksternal.');
            $table->bigIncrements('integration_callback_log_id')->comment('ID audit callback');
            $table->string('integration_callback_log_provider', 30)->comment('Kode provider pengirim callback');
            $table->string('integration_callback_log_type', 30)->comment('Jenis integrasi callback');
            $table->string('integration_callback_log_event', 50)->comment('Nama event dari provider');
            $table->json('integration_callback_log_headers_json')->nullable()->comment('Header aman callback tanpa kredensial');
            $table->json('integration_callback_log_payload_json')->comment('Payload callback tervalidasi');
            $table->json('integration_callback_log_response_json')->nullable()->comment('Ringkasan hasil pemrosesan callback');
            $table->enum('integration_callback_log_status', ['processing', 'completed', 'partial', 'failed'])
                ->default('processing')
                ->comment('Status pemrosesan callback');
            $table->unsignedSmallInteger('integration_callback_log_http_code')->nullable()->comment('Kode HTTP respons callback');
            $table->dateTime('integration_callback_log_processed_datetime')->nullable()->comment('Waktu callback selesai diproses');
            $table->dateTime('integration_callback_log_created_datetime')->comment('Waktu callback diterima');

            $table->index(
                ['integration_callback_log_provider', 'integration_callback_log_type', 'integration_callback_log_created_datetime'],
                'idx_callback_provider_type_created'
            );
        });

        Schema::table('shipping_courier_express', function (Blueprint $table): void {
            $table->index('shipping_courier_express_order_id', 'idx_express_order_id');
        });
        Schema::table('shipping_courier_instant', function (Blueprint $table): void {
            $table->index('shipping_courier_instant_order_id', 'idx_instant_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_courier_instant', function (Blueprint $table): void {
            $table->dropIndex('idx_instant_order_id');
        });
        Schema::table('shipping_courier_express', function (Blueprint $table): void {
            $table->dropIndex('idx_express_order_id');
        });
        Schema::dropIfExists('integration_callback_log');
    }
};
