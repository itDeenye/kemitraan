<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $temporaryPrefix = 'TMP-'.Str::lower(Str::random(8)).'-';

            DB::table('member')
                ->select('member_id')
                ->orderBy('member_id')
                ->chunkById(500, function ($members) use ($temporaryPrefix): void {
                    foreach ($members as $member) {
                        DB::table('member')
                            ->where('member_id', $member->member_id)
                            ->update([
                                'member_code' => $temporaryPrefix.$member->member_id,
                            ]);
                    }
                }, 'member_id');

            $sequence = 0;

            DB::table('member')
                ->select('member_id')
                ->orderBy('member_id')
                ->chunkById(500, function ($members) use (&$sequence): void {
                    foreach ($members as $member) {
                        $sequence++;

                        DB::table('member')
                            ->where('member_id', $member->member_id)
                            ->update([
                                'member_code' => 'DNY-'.Str::padLeft((string) $sequence, 6, '0'),
                            ]);
                    }
                }, 'member_id');

            $now = now();
            $sequenceConfig = DB::table('config')
                ->where('config_key', 'partnership.member_code_sequence');

            if ($sequenceConfig->exists()) {
                $sequenceConfig->update([
                    'config_value' => (string) $sequence,
                    'config_type' => 'integer',
                    'config_updated_datetime' => $now,
                ]);

                return;
            }

            DB::table('config')->insert([
                'config_key' => 'partnership.member_code_sequence',
                'config_value' => (string) $sequence,
                'config_type' => 'integer',
                'config_created_datetime' => $now,
                'config_updated_datetime' => $now,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new \RuntimeException(
            'Normalisasi kode member ke urutan global tidak dapat dikembalikan secara otomatis.',
        );
    }
};
