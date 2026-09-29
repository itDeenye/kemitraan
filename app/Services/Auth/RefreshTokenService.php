<?php

namespace App\Services\Auth;

use App\Exceptions\ProcessException;
use App\Models\AuthRefreshToken;
use App\Models\MemberAccount;
use App\Models\SiteAdministrator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RefreshTokenService
{
    private const ADMIN = 'admin';

    private const MEMBER = 'member';

    /**
     * @return array{access_token: string, refresh_token: string, expires_at: string, refresh_token_expires_at: string}
     */
    public function issue(
        SiteAdministrator|MemberAccount $account,
        string $ownerType,
        string $deviceName,
    ): array {
        $this->assertOwnerType($ownerType);

        return DB::transaction(function () use ($account, $ownerType, $deviceName): array {
            [$refreshToken, $refreshTokenModel] = $this->createRefreshToken(
                $ownerType,
                (int) $account->getKey(),
                $deviceName,
            );

            return $this->tokenResponse($account, $ownerType, $refreshTokenModel, $refreshToken);
        });
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_at: string, refresh_token_expires_at: string}
     */
    public function rotate(string $ownerType, string $plainRefreshToken): array
    {
        $this->assertOwnerType($ownerType);

        return DB::transaction(function () use ($ownerType, $plainRefreshToken): array {
            $current = AuthRefreshToken::query()
                ->where('refresh_token_owner_type', $ownerType)
                ->where('refresh_token_hash', hash('sha256', $plainRefreshToken))
                ->lockForUpdate()
                ->first();

            if (! $current
                || $current->refresh_token_revoked_at !== null
                || $current->refresh_token_expires_at->isPast()) {
                throw new ProcessException('Sesi Anda telah berakhir. Silakan masuk kembali.', 401);
            }

            $account = $this->activeAccount($ownerType, (int) $current->refresh_token_owner_id);
            $current->update([
                'refresh_token_last_used_at' => now(),
                'refresh_token_revoked_at' => now(),
            ]);

            [$refreshToken, $refreshTokenModel] = $this->createRefreshToken(
                $ownerType,
                (int) $account->getKey(),
                $current->refresh_token_device_name,
            );

            return $this->tokenResponse($account, $ownerType, $refreshTokenModel, $refreshToken);
        });
    }

    public function revokeCurrentSession(string $ownerType, int $ownerId): void
    {
        $this->assertOwnerType($ownerType);
        $guard = Auth::guard($this->guard($ownerType));
        $refreshTokenId = (int) ($guard->payload()->get('refresh_token_id') ?? 0);

        if ($refreshTokenId === 0) {
            return;
        }

        AuthRefreshToken::query()
            ->whereKey($refreshTokenId)
            ->where('refresh_token_owner_type', $ownerType)
            ->where('refresh_token_owner_id', $ownerId)
            ->whereNull('refresh_token_revoked_at')
            ->update(['refresh_token_revoked_at' => now()]);
    }

    /** @return array{string, AuthRefreshToken} */
    private function createRefreshToken(string $ownerType, int $ownerId, string $deviceName): array
    {
        $plainToken = bin2hex(random_bytes(48));
        $expiresAt = now()->addDays((int) config('dny_auth.refresh_token_expiration_days'));
        $token = AuthRefreshToken::query()->create([
            'refresh_token_owner_type' => $ownerType,
            'refresh_token_owner_id' => $ownerId,
            'refresh_token_hash' => hash('sha256', $plainToken),
            'refresh_token_device_name' => $deviceName,
            'refresh_token_expires_at' => $expiresAt,
            'refresh_token_created_at' => now(),
        ]);

        return [$plainToken, $token];
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_at: string, refresh_token_expires_at: string}
     */
    private function tokenResponse(
        SiteAdministrator|MemberAccount $account,
        string $ownerType,
        AuthRefreshToken $refreshTokenModel,
        string $plainRefreshToken,
    ): array {
        $accessToken = Auth::guard($this->guard($ownerType))
            ->claims([
                'device_name' => $refreshTokenModel->refresh_token_device_name,
                'refresh_token_id' => (int) $refreshTokenModel->getKey(),
            ])
            ->login($account);

        if (! is_string($accessToken) || $accessToken === '') {
            throw new ProcessException('Sesi baru tidak dapat dibuat. Silakan masuk kembali.', 500);
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => $plainRefreshToken,
            'expires_at' => now()->addMinutes((int) config('jwt.ttl'))->toAtomString(),
            'refresh_token_expires_at' => $refreshTokenModel->refresh_token_expires_at->toAtomString(),
        ];
    }

    private function activeAccount(string $ownerType, int $ownerId): SiteAdministrator|MemberAccount
    {
        if ($ownerType === self::ADMIN) {
            $account = SiteAdministrator::query()->with('group')->find($ownerId);
            if (! $account
                || ! $account->administrator_is_active
                || ! $account->group?->administrator_group_is_active) {
                throw new ProcessException('Akun administrator tidak aktif.', 401);
            }

            return $account;
        }

        $account = MemberAccount::query()->with(['member.level', 'group'])->find($ownerId);
        if (! $account
            || $account->member?->member_status !== 1
            || ! $account->member?->level?->member_level_is_active
            || ! $account->group?->member_group_is_active) {
            throw new ProcessException('Akun mitra tidak aktif.', 401);
        }

        return $account;
    }

    private function guard(string $ownerType): string
    {
        return $ownerType === self::ADMIN ? 'admin_api' : 'member_api';
    }

    private function assertOwnerType(string $ownerType): void
    {
        if (! in_array($ownerType, [self::ADMIN, self::MEMBER], true)) {
            throw new ProcessException('Data sesi tidak dapat diproses. Silakan masuk kembali.', 500);
        }
    }
}
