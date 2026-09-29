<?php

namespace App\Support;

final class ShippingInsurance
{
    /** @param array<string, mixed> $courier */
    public static function isForced(array $courier): bool
    {
        return filter_var(
            $courier['force_insurance'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    /** @param array<string, mixed> $courier */
    public static function amount(array $courier): int
    {
        if (! self::isForced($courier)) {
            return 0;
        }

        return max(0, (int) ($courier['insurance'] ?? 0));
    }
}
