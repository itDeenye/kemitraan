<?php

namespace App\Services\Auth;

use App\Exceptions\AccountLockedException;
use App\Exceptions\InvalidCredentialsException;
use App\Models\MemberAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MemberAuthService
{
    public function __construct(private readonly RefreshTokenService $refreshTokenService) {}

    /**
     * @return array{account: MemberAccount, access_token: string, refresh_token: string, expires_at: string, refresh_token_expires_at: string}
     */
    public function login(string $username, string $password, string $deviceName): array
    {
        $result = DB::transaction(function () use ($username, $password): array {
            $account = MemberAccount::query()
                ->with(['member.level', 'member.stockist', 'group'])
                ->where('member_account_username', $username)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                return ['status' => 'invalid'];
            }

            if ($account->member_account_locked_until?->isFuture()) {
                return ['status' => 'locked', 'account' => $account];
            }

            if (! Hash::check($password, $account->member_account_password)) {
                return $this->recordFailedAttempt($account);
            }

            if ($account->member?->member_status !== 1
                || ! $account->member?->level?->member_level_is_active
                || ! $account->group?->member_group_is_active) {
                return ['status' => 'invalid'];
            }

            $account->forceFill([
                'member_account_failed_login_attempts' => 0,
                'member_account_last_failed_login_datetime' => null,
                'member_account_locked_until' => null,
                'member_account_last_login_datetime' => now(),
            ])->save();

            return ['status' => 'success', 'account' => $account];
        });

        if ($result['status'] === 'locked') {
            throw new AccountLockedException($result['account']->member_account_locked_until);
        }

        if ($result['status'] !== 'success') {
            throw new InvalidCredentialsException;
        }

        $tokens = $this->refreshTokenService->issue($result['account'], 'member', $deviceName);

        return [
            'account' => $result['account'],
            ...$tokens,
        ];
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_at: string, refresh_token_expires_at: string}
     */
    public function refresh(string $refreshToken): array
    {
        return $this->refreshTokenService->rotate('member', $refreshToken);
    }

    public function logout(MemberAccount $account): void
    {
        if (Auth::guard('member_api')->check()) {
            $this->refreshTokenService->revokeCurrentSession('member', (int) $account->getKey());
            Auth::guard('member_api')->logout();

            return;
        }

        $account->currentAccessToken()?->delete();
    }

    /**
     * @return array{status: string, account?: MemberAccount}
     */
    private function recordFailedAttempt(MemberAccount $account): array
    {
        $attempts = $account->member_account_locked_until?->isPast()
            ? 1
            : $account->member_account_failed_login_attempts + 1;

        $lockedUntil = $attempts >= config('dny_auth.max_login_attempts')
            ? now()->addMinutes(config('dny_auth.lockout_minutes'))
            : null;

        $account->forceFill([
            'member_account_failed_login_attempts' => $attempts,
            'member_account_last_failed_login_datetime' => now(),
            'member_account_locked_until' => $lockedUntil,
        ])->save();

        return $lockedUntil
            ? ['status' => 'locked', 'account' => $account]
            : ['status' => 'invalid'];
    }
}
