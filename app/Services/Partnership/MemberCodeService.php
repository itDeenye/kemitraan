<?php

namespace App\Services\Partnership;

use App\Exceptions\ProcessException;
use App\Models\Member;
use App\Models\MemberLevel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MemberCodeService
{
    public function next(MemberLevel $targetLevel, ?Member $sponsor = null): string
    {
        return DB::transaction(function () use ($targetLevel, $sponsor): string {
            $members = Member::query()
                ->select(['member_id', 'member_code'])
                ->lockForUpdate()
                ->get();

            return match ($targetLevel->member_level_code) {
                'DST' => $this->format(
                    $this->nextSequence($members, fn (array $segments): bool => $segments[0] > 0
                        && $segments[1] === 0
                        && $segments[2] === 0),
                    0,
                    0,
                ),
                'AGT' => $this->nextAgentCode($members, $sponsor),
                'RSL' => $this->nextResellerCode($members, $sponsor),
                default => throw new ProcessException('Tingkat mitra tidak didukung untuk pembuatan kode.'),
            };
        });
    }

    /** @param Collection<int, Member> $members */
    private function nextAgentCode(Collection $members, ?Member $sponsor): string
    {
        $distributorSequence = $sponsor === null
            ? 0
            : $this->segments($sponsor->member_code)[0];

        if ($sponsor !== null && $distributorSequence === 0) {
            throw new ProcessException('Kode Distributor sponsor tidak valid untuk pendaftaran Agent.');
        }

        return $this->format(
            $distributorSequence,
            $this->nextSequence(
                $members,
                fn (array $segments): bool => $segments[0] === $distributorSequence && $segments[1] > 0,
                1,
            ),
            0,
        );
    }

    /** @param Collection<int, Member> $members */
    private function nextResellerCode(Collection $members, ?Member $sponsor): string
    {
        if ($sponsor === null) {
            throw new ProcessException('Sponsor Agent wajib tersedia untuk pendaftaran Reseller.');
        }

        [$distributorSequence, $agentSequence] = $this->segments($sponsor->member_code);
        if ($agentSequence === 0) {
            throw new ProcessException('Kode Agent sponsor tidak valid untuk pendaftaran Reseller.');
        }

        return $this->format(
            $distributorSequence,
            $agentSequence,
            $this->nextSequence(
                $members,
                fn (array $segments): bool => $segments[0] === $distributorSequence
                    && $segments[1] === $agentSequence,
                2,
            ),
        );
    }

    /**
     * @param Collection<int, Member> $members
     * @param callable(array{int, int, int}): bool $matches
     */
    private function nextSequence(Collection $members, callable $matches, int $segmentIndex = 0): int
    {
        $sequence = $members
            ->map(fn (Member $member): array => $this->segments($member->member_code))
            ->filter($matches)
            ->map(fn (array $segments): int => $segments[$segmentIndex])
            ->max() ?? 0;

        return $sequence + 1;
    }

    /** @return array{int, int, int} */
    private function segments(?string $code): array
    {
        if (is_string($code) && preg_match('/^(\d{4})\/(\d{4})\/(\d{4})$/', $code, $matches) === 1) {
            return [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
        }

        return [0, 0, 0];
    }

    private function format(int $distributor, int $agent, int $reseller): string
    {
        if (min($distributor, $agent, $reseller) < 0 || max($distributor, $agent, $reseller) > 9999) {
            throw new ProcessException('Nomor urut kode mitra telah mencapai batas maksimum.');
        }

        return implode('/', array_map(
            fn (int $sequence): string => str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            [$distributor, $agent, $reseller],
        ));
    }
}
