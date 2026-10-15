<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->changeTypes(['KTP', 'KK', 'SIM', 'BPJS', 'BPJS_KETENAGAKERJAAN', 'PASPOR']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['member' => 'member_identity_type', 'member_registration' => 'member_registration_identity_type'] as $table => $column) {
            if (DB::table($table)->whereIn($column, ['KK', 'BPJS_KETENAGAKERJAAN'])->exists()) {
                throw new LogicException('Cannot remove identity types while KK or BPJS Ketenagakerjaan records exist.');
            }
        }
        $this->changeTypes(['KTP', 'SIM', 'BPJS', 'PASPOR']);
    }

    /** @param array<int, string> $types */
    private function changeTypes(array $types): void
    {
        Schema::table('member', function (Blueprint $table) use ($types): void {
            $table->enum('member_identity_type', $types)->default('KTP')->comment('Jenis Identitas Member')->change();
        });
        Schema::table('member_registration', function (Blueprint $table) use ($types): void {
            $table->enum('member_registration_identity_type', $types)->default('KTP')->comment('Jenis Identitas')->change();
        });
    }
};
