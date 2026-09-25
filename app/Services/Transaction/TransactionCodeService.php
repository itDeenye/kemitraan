<?php

namespace App\Services\Transaction;

use App\Models\Trx;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TransactionCodeService
{
    private const UNIQUE_SUFFIX_LENGTH = 6;

    /** @var array<string, string> */
    private const PARTY_CODES = [
        'warehouse' => 'CMP',
        'distributor' => 'DST',
        'agent' => 'AGT',
        'reseller' => 'RSL',
        'customer' => 'CUS',
    ];

    public function next(
        string $sellerType,
        string $buyerType,
        ?string $uniqueSuffix = null,
    ): string {
        return $this->generate($sellerType, $buyerType, $uniqueSuffix);
    }

    public function nextInChain(
        string $sourceCode,
        string $sellerType,
        string $buyerType,
        ?string $uniqueSuffix = null,
    ): string {
        if (preg_match('#^TRX/[^/]+/[^/]+/(\d{6})/[A-Z0-9]{6}$#', $sourceCode, $match) !== 1) {
            throw new InvalidArgumentException('Kode transaksi sumber tidak valid.');
        }

        return $this->generate(
            $sellerType,
            $buyerType,
            $uniqueSuffix,
            (int) $match[1],
        );
    }

    private function generate(
        string $sellerType,
        string $buyerType,
        ?string $uniqueSuffix = null,
        ?int $sequence = null,
    ): string {
        $sellerCode = $this->partyCode($sellerType);
        $buyerCode = $this->partyCode($buyerType);
        $suffix = $uniqueSuffix === null
            ? Str::upper(Str::random(self::UNIQUE_SUFFIX_LENGTH))
            : Str::upper($uniqueSuffix);

        if (preg_match('/^[A-Z0-9]{6}$/', $suffix) !== 1) {
            throw new InvalidArgumentException('Kode unik transaksi wajib terdiri dari 6 karakter alfanumerik.');
        }

        return DB::transaction(function () use ($buyerCode, $sellerCode, $sequence, $suffix): string {
            $nextSequence = $sequence;
            if ($nextSequence === null) {
                $codes = Trx::query()->lockForUpdate()->pluck('trx_code');
                $nextSequence = $codes
                    ->map(fn (mixed $code): int => preg_match(
                        '#^TRX/[^/]+/[^/]+/(\d+)/#',
                        (string) $code,
                        $match,
                    ) === 1 ? (int) $match[1] : 0)
                    ->max() + 1;
            }

            return implode('/', [
                'TRX',
                $sellerCode,
                $buyerCode,
                Str::padLeft((string) $nextSequence, 6, '0'),
                $suffix,
            ]);
        });
    }

    public static function deterministicUniqueSuffix(string $key): string
    {
        return Str::upper(substr(hash('sha256', $key), 0, self::UNIQUE_SUFFIX_LENGTH));
    }

    private function partyCode(string $partyType): string
    {
        return self::PARTY_CODES[$partyType]
            ?? throw new InvalidArgumentException("Tipe pihak transaksi {$partyType} tidak didukung.");
    }
}
