<?php

namespace App\Services\Document;

use App\Models\GoodsReceive;
use App\Models\ReturnModel;
use App\Models\WarehouseStockAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DocumentCodeService
{
    public const ADJUSTMENT = 'ADJ';

    public const GOODS_RECEIVE = 'GRN';

    public const RETURN = 'RTR';

    private const UNIQUE_SUFFIX_LENGTH = 6;

    private const PREFIXES = [
        self::ADJUSTMENT,
        self::GOODS_RECEIVE,
        self::RETURN,
    ];

    public function next(string $prefix, ?string $uniqueSuffix = null): string
    {
        $normalizedPrefix = Str::upper($prefix);
        if (! in_array($normalizedPrefix, self::PREFIXES, true)) {
            throw new InvalidArgumentException("Prefix dokumen {$prefix} tidak didukung.");
        }

        $suffix = $uniqueSuffix === null
            ? Str::upper(Str::random(self::UNIQUE_SUFFIX_LENGTH))
            : Str::upper($uniqueSuffix);
        if (preg_match('/^[A-Z0-9]{6}$/', $suffix) !== 1) {
            throw new InvalidArgumentException('Kode unik dokumen wajib terdiri dari 6 karakter alfanumerik.');
        }

        return DB::transaction(function () use ($normalizedPrefix, $suffix): string {
            [$model, $column] = $this->sequenceSource($normalizedPrefix);
            $codes = $model::query()->lockForUpdate()->pluck($column);
            $nextSequence = $codes
                ->map(fn (mixed $code): int => preg_match(
                    '#^'.preg_quote($normalizedPrefix, '#').'/?(\d+)#',
                    (string) $code,
                    $match,
                ) === 1 ? (int) $match[1] : 0)
                ->max() + 1;

            return implode('/', [
                $normalizedPrefix,
                Str::padLeft((string) $nextSequence, 6, '0'),
                $suffix,
            ]);
        });
    }

    public static function deterministicUniqueSuffix(string $key): string
    {
        return Str::upper(Str::substr(hash('sha256', $key), 0, self::UNIQUE_SUFFIX_LENGTH));
    }

    /** @return array{0: class-string, 1: string} */
    private function sequenceSource(string $prefix): array
    {
        return match ($prefix) {
            self::GOODS_RECEIVE => [GoodsReceive::class, 'goods_receive_number'],
            self::RETURN => [ReturnModel::class, 'return_code'],
            self::ADJUSTMENT => [WarehouseStockAdjustment::class, 'stock_adjustment_code'],
        };
    }
}
