<?php

namespace App\Support;

use App\Models\Member;
use App\Models\MemberRegistration;

final class MemberPhoneNumber
{
    public static function isUsed(mixed $phone, ?Member $existing = null, ?MemberRegistration $registration = null): bool
    {
        if (! is_string($phone) || blank($phone = PhoneNumber::normalize($phone))) {
            return false;
        }

        $variants = PhoneNumber::isValid($phone)
            ? [$phone, substr($phone, 1), '0'.substr($phone, 3)]
            : [$phone];

        if (Member::query()->whereIn('member_mobilephone', $variants)
            ->where('member_status', '!=', 3)
            ->when($existing !== null, fn ($query) => $query->where('member_id', '!=', $existing->getKey()))
            ->exists()) {
            return true;
        }

        return MemberRegistration::query()->whereIn('member_registration_mobilephone', $variants)
            ->whereIn('member_registration_status', ['requested', 'approved'])
            ->when($registration !== null, fn ($query) => $query->where('member_registration_id', '!=', $registration->getKey()))
            ->when($existing !== null, fn ($query) => $query->where(fn ($query) => $query
                ->whereNull('member_registration_member_id')
                ->orWhere('member_registration_member_id', '!=', $existing->getKey())))
            ->exists();
    }
}
