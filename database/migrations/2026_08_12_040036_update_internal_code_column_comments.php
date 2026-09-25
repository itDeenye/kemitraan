<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE member MODIFY COLUMN member_code VARCHAR(30) NOT NULL COMMENT 'Kode unik member berurutan global dengan format DNY000001'",
        );
        DB::statement(
            "ALTER TABLE trx MODIFY COLUMN trx_code VARCHAR(50) NOT NULL COMMENT 'Kode transaksi global: TRX/{penjual}/{pembeli}/{urutan}/{unik}'",
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE member MODIFY COLUMN member_code VARCHAR(30) NOT NULL COMMENT 'Kode unik member berurutan global dengan format DNY-000001'",
        );
        DB::statement(
            "ALTER TABLE trx MODIFY COLUMN trx_code VARCHAR(50) NOT NULL COMMENT 'Kode transaksi global: TRX-{penjual}-{pembeli}-{urutan}-{unik}'",
        );
    }
};
