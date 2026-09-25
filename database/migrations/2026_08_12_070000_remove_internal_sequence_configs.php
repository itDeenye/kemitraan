<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('config')->whereIn('config_key', [
            'partnership.member_code_sequence',
            'transaction.code_sequence',
            'document_code.rtr_sequence',
            'document_code.grn_sequence',
            'document_code.adj_sequence',
            'document_code.opn_sequence',
            'document_code.trf_sequence',
        ])->delete();
    }

    public function down(): void
    {
        // Sequence values are derived from their source tables and are not
        // restored as editable configuration rows.
    }
};
