<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
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
            $members = DB::table('member')
                ->leftJoin(
                    'member_level',
                    'member_level.member_level_id',
                    '=',
                    'member.member_member_level_id',
                )
                ->select([
                    'member.member_id',
                    'member.member_code',
                    'member.member_parent_member_id',
                    'member_level.member_level_code',
                ])
                ->orderBy('member.member_id')
                ->lockForUpdate()
                ->get();

            if ($members->isEmpty() || $members->every(fn (object $member): bool => $this->isHierarchicalCode($member->member_code))) {
                return;
            }

            $codes = $this->codesForMembers($members);
            $temporaryPrefix = 'TMP-'.Str::lower(Str::random(8)).'-';

            foreach ($members as $member) {
                DB::table('member_account')
                    ->where('member_account_member_id', $member->member_id)
                    ->where('member_account_username', $member->member_code)
                    ->update([
                        'member_account_username' => $temporaryPrefix.$member->member_id,
                    ]);
                DB::table('member')
                    ->where('member_id', $member->member_id)
                    ->update(['member_code' => $temporaryPrefix.$member->member_id]);
            }

            foreach ($members as $member) {
                $memberId = (int) $member->member_id;
                $code = $codes[$memberId];

                DB::table('member')
                    ->where('member_id', $memberId)
                    ->update(['member_code' => $code]);
                DB::table('member_account')
                    ->where('member_account_member_id', $memberId)
                    ->where('member_account_username', $temporaryPrefix.$memberId)
                    ->update(['member_account_username' => $code]);
                DB::table('member_registration')
                    ->where('member_registration_member_id', $memberId)
                    ->where('member_registration_username', $member->member_code)
                    ->update(['member_registration_username' => $code]);
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE member MODIFY COLUMN member_code VARCHAR(30) NOT NULL COMMENT 'Unique ID mitra format aaaa/bbbb/cccc'",
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new \RuntimeException('Konversi kode mitra hierarkis tidak dapat dikembalikan secara otomatis.');
    }

    /** @return array<int, string> */
    private function codesForMembers(Collection $members): array
    {
        $membersById = $members->keyBy('member_id');
        $codes = [];
        $distributorSequence = 0;

        foreach ($members->where('member_level_code', 'DST') as $member) {
            $distributorSequence++;
            $codes[(int) $member->member_id] = $this->format($distributorSequence, 0, 0);
        }

        /** @var array<int, int> $agentSequences */
        $agentSequences = [];
        foreach ($members->where('member_level_code', 'AGT') as $member) {
            $distributor = $this->distributorSequenceFor($member, $membersById, $codes);
            $agentSequences[$distributor] = ($agentSequences[$distributor] ?? 0) + 1;
            $codes[(int) $member->member_id] = $this->format(
                $distributor,
                $agentSequences[$distributor],
                0,
            );
        }

        /** @var array<string, int> $resellerSequences */
        $resellerSequences = [];
        foreach ($members->where('member_level_code', 'RSL') as $member) {
            [$distributor, $agent] = $this->segments(
                $codes[(int) $member->member_parent_member_id] ?? null,
            );
            $key = "{$distributor}/{$agent}";
            $resellerSequences[$key] = ($resellerSequences[$key] ?? 0) + 1;
            $codes[(int) $member->member_id] = $this->format(
                $distributor,
                $agent,
                $resellerSequences[$key],
            );
        }

        foreach ($members as $member) {
            $memberId = (int) $member->member_id;
            if (isset($codes[$memberId])) {
                continue;
            }

            $key = '0/0';
            $resellerSequences[$key] = ($resellerSequences[$key] ?? 0) + 1;
            $codes[$memberId] = $this->format(0, 0, $resellerSequences[$key]);
        }

        return $codes;
    }

    /** @param Collection<int, object> $membersById @param array<int, string> $codes */
    private function distributorSequenceFor(object $member, Collection $membersById, array $codes): int
    {
        $parentId = (int) $member->member_parent_member_id;

        for ($depth = 0; $depth < 100 && $parentId !== 0; $depth++) {
            $parent = $membersById->get($parentId);
            if ($parent === null) {
                return 0;
            }

            if ($parent->member_level_code === 'DST') {
                return $this->segments($codes[(int) $parent->member_id] ?? null)[0];
            }

            $parentId = (int) $parent->member_parent_member_id;
        }

        return 0;
    }

    private function isHierarchicalCode(mixed $code): bool
    {
        return is_string($code) && preg_match('/^\d{4}\/\d{4}\/\d{4}$/', $code) === 1;
    }

    /** @return array{int, int} */
    private function segments(?string $code): array
    {
        if (is_string($code) && preg_match('/^(\d{4})\/(\d{4})\/(\d{4})$/', $code, $matches) === 1) {
            return [(int) $matches[1], (int) $matches[2]];
        }

        return [0, 0];
    }

    private function format(int $distributor, int $agent, int $reseller): string
    {
        if (max($distributor, $agent, $reseller) > 9999) {
            throw new \RuntimeException('Nomor urut kode mitra telah mencapai batas maksimum.');
        }

        return implode('/', array_map(
            fn (int $sequence): string => str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            [$distributor, $agent, $reseller],
        ));
    }
};
