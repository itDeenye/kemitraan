<?php

namespace App\Support;

use App\Exceptions\ProcessException;

final class PointCalculation
{
    private const MAX_EXACT_NUMBER = 9_007_199_254_740_991;

    public static function integer(mixed $value): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT);
        if ($number === false || $number < 0 || $number > self::MAX_EXACT_NUMBER) {
            throw new ProcessException('Total poin atau reward melebihi batas angka yang dapat dihitung dengan tepat.');
        }

        return $number;
    }

    public static function multiply(int $quantity, int $value): int
    {
        self::integer($quantity);
        self::integer($value);
        if ($quantity > 0 && $value > intdiv(self::MAX_EXACT_NUMBER, $quantity)) {
            throw new ProcessException('Total poin atau reward melebihi batas angka yang dapat dihitung dengan tepat.');
        }

        return $quantity * $value;
    }

    /** @param iterable<mixed> $values */
    public static function sum(iterable $values): int
    {
        $total = 0;
        foreach ($values as $value) {
            $number = self::integer($value);
            if ($number > self::MAX_EXACT_NUMBER - $total) {
                throw new ProcessException('Total poin atau reward melebihi batas angka yang dapat dihitung dengan tepat.');
            }
            $total += $number;
        }

        return $total;
    }
}
