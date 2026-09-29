<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('warehouse_stock_opname_detail');
        Schema::dropIfExists('warehouse_stock_opname');
    }

    public function down(): void
    {
        throw new RuntimeException('Tabel stock opname tidak dapat dipulihkan otomatis.');
    }
};
