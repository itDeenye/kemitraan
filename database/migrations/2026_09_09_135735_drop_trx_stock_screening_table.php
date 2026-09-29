<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('trx_stock_screening');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('trx_stock_screening', function (Blueprint $table): void {
            $table->increments('stock_screening_id');
            $table->unsignedInteger('stock_screening_trx_id')->unique();
            $table->enum('stock_screening_status', ['pending', 'approved', 'rejected', 'cancelled'])
                ->default('pending');
            $table->unsignedInteger('stock_screening_administrator_id')->default(0);
            $table->text('stock_screening_note')->nullable();
            $table->dateTime('stock_screening_requested_datetime');
            $table->dateTime('stock_screening_reviewed_datetime')->nullable();

            $table->index(
                ['stock_screening_status', 'stock_screening_requested_datetime'],
                'trx_stock_screening_status_requested_index',
            );
        });
    }
};
