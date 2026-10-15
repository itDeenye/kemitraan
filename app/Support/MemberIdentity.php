<?php

namespace App\Support;

use App\Models\Member;
use Illuminate\Validation\Validator;

final class MemberIdentity
{
    public const TYPES = ['KTP', 'KK', 'SIM', 'BPJS', 'BPJS_KETENAGAKERJAAN', 'PASPOR'];

    public static function validateNumber(Validator $validator, mixed $type, mixed $number, ?Member $existing = null): void
    {
        if (! is_string($type) || ! is_string($number) || $number === '') {
            return;
        }
        if ($existing !== null && $type === $existing->member_identity_type && $number === $existing->member_identity_no) {
            return;
        }

        [$pattern, $message] = match ($type) {
            'KTP', 'KK', 'SIM' => ['/\A[0-9]{16}\z/', "Nomor {$type} harus terdiri dari 16 digit angka."],
            'BPJS' => ['/\A[0-9]{13}\z/', 'Nomor BPJS Kesehatan harus terdiri dari 13 digit angka.'],
            'BPJS_KETENAGAKERJAAN' => ['/\A(?:[0-9]{11}|[0-9]{16})\z/', 'Nomor BPJS Ketenagakerjaan harus terdiri dari 11 atau 16 digit angka.'],
            'PASPOR' => ['/\A[A-Za-z0-9]{8}\z/', 'Nomor paspor harus terdiri dari 8 karakter huruf atau angka.'],
            default => [null, null],
        };
        if ($pattern !== null && preg_match($pattern, $number) !== 1) {
            $validator->errors()->add('identity_no', $message);
        }
    }
}
