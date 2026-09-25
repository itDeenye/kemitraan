<?php

namespace App\Services\Auth;

use App\Exceptions\AccountLockedException;
use App\Exceptions\InvalidCredentialsException;
use App\Models\SiteAdministrator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminAuthService
{
    public function __construct(private readonly RefreshTokenService $refreshTokenService) {}

    /**
     * @return array{account: SiteAdministrator, access_token: string, refresh_token: string, expires_at: string, refresh_token_expires_at: string}
     */
    public function login(string $username, string $password, string $deviceName): array
    {
        $administrator = $this->authenticate($username, $password);
        $tokens = $this->refreshTokenService->issue($administrator, 'admin', $deviceName);

        return [
            'account' => $administrator,
            ...$tokens,
        ];
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_at: string, refresh_token_expires_at: string}
     */
    public function refresh(string $refreshToken): array
    {
        return $this->refreshTokenService->rotate('admin', $refreshToken);
    }

    public function authenticate(string $username, string $password): SiteAdministrator
    {
        $result = DB::transaction(function () use ($username, $password): array {
            $administrator = SiteAdministrator::query()
                ->with('group')
                ->where('administrator_username', $username)
                ->lockForUpdate()
                ->first();

            if (! $administrator) {
                return ['status' => 'invalid'];
            }

            if ($administrator->administrator_locked_until?->isFuture()) {
                return ['status' => 'locked', 'account' => $administrator];
            }

            if (! Hash::check($password, $administrator->administrator_password)) {
                return $this->recordFailedAttempt($administrator);
            }

            if (! $administrator->administrator_is_active || ! $administrator->group?->administrator_group_is_active) {
                return ['status' => 'invalid'];
            }

            $administrator->forceFill([
                'administrator_failed_login_attempts' => 0,
                'administrator_last_failed_login_datetime' => null,
                'administrator_locked_until' => null,
                'administrator_last_login' => now(),
            ])->save();

            return ['status' => 'success', 'account' => $administrator];
        });

        if ($result['status'] === 'locked') {
            throw new AccountLockedException($result['account']->administrator_locked_until);
        }

        if ($result['status'] !== 'success') {
            throw new InvalidCredentialsException;
        }

        return $result['account'];
    }

    public function logout(SiteAdministrator $administrator): void
    {
        if (Auth::guard('admin_api')->check()) {
            $this->refreshTokenService->revokeCurrentSession('admin', (int) $administrator->getKey());
            Auth::guard('admin_api')->logout();

            return;
        }

        $administrator->currentAccessToken()?->delete();
    }

    /**
     * @return array{status: string, account?: SiteAdministrator}
     */
    private function recordFailedAttempt(SiteAdministrator $administrator): array
    {
        $attempts = $administrator->administrator_locked_until?->isPast()
            ? 1
            : $administrator->administrator_failed_login_attempts + 1;

        $lockedUntil = $attempts >= config('dny_auth.max_login_attempts')
            ? now()->addMinutes(config('dny_auth.lockout_minutes'))
            : null;

        $administrator->forceFill([
            'administrator_failed_login_attempts' => $attempts,
            'administrator_last_failed_login_datetime' => now(),
            'administrator_locked_until' => $lockedUntil,
        ])->save();

        return $lockedUntil
            ? ['status' => 'locked', 'account' => $administrator]
            : ['status' => 'invalid'];
    }
}
