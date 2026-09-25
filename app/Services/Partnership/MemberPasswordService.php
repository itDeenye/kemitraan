<?php

namespace App\Services\Partnership;

use App\Models\MemberAccount;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Hash;

class MemberPasswordService
{
    public function birthDatePassword(CarbonInterface|string $birthDate): string
    {
        $birthDate = $birthDate instanceof CarbonInterface
            ? CarbonImmutable::instance($birthDate)
            : CarbonImmutable::parse($birthDate);

        return $birthDate->format('dmY');
    }

    public function approvalPassword(CarbonInterface|string $birthDate): string
    {
        if (app()->environment(['local', 'development', 'testing'])) {
            $password = (string) config('initial_data.development_approval_password', '');

            if ($password !== '') {
                return $password;
            }
        }

        return $this->birthDatePassword($birthDate);
    }

    public function isBirthDatePassword(MemberAccount $account): bool
    {
        $birthDate = $account->member?->member_birth_date;

        return $birthDate !== null
            && Hash::check(
                $this->birthDatePassword($birthDate),
                (string) $account->member_account_password,
            );
    }
}
