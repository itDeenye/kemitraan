<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('warehouse_stock_transfer_detail');
        Schema::dropIfExists('warehouse_stock_transfer');
    }

    /**
     * The obsolete warehouse transfer feature is intentionally not restored.
     */
    public function down(): void {}
};
