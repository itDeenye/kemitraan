<?php

namespace App\Support;

final class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        $value = trim((string) $phone);
        if ($value === '') {
            return null;
        }
        $value = preg_replace('/[^0-9+]/', '', $value) ?? '';
        if (str_starts_with($value, '+62')) {
            return '+62'.substr($value, 3);
        }
        if (str_starts_with($value, '62')) {
            return '+'.$value;
        }
        if (str_starts_with($value, '0')) {
            return '+62'.substr($value, 1);
        }

        return $value;
    }

    public static function isValid(?string $phone): bool
    {
        return is_string($phone) && preg_match('/^\+62[0-9]{8,13}$/', $phone) === 1;
    }
}
