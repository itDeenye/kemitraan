<?php

namespace App\Services\Auth;

use App\Exceptions\ProcessException;
use App\Mail\PasswordResetMail;
use App\Models\AuthRefreshToken;
use App\Models\MemberAccount;
use App\Models\SiteAdministrator;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use InvalidArgumentException;
use Throwable;

class PasswordResetService
{
    private const ADMIN = 'admin';

    private const MEMBER = 'member';

    public function sendResetLink(string $audience, string $identifier): void
    {
        $this->assertAudience($audience);
        $account = $audience === self::MEMBER
            ? $this->findActiveMemberAccountByIdentifier($identifier)
            : $this->findActiveAdminAccountByIdentifier($identifier);

        if (! $account) {
            return;
        }

        $broker = $this->broker($audience);
        if ($broker->getRepository()->recentlyCreatedToken($account)) {
            return;
        }

        $token = $broker->createToken($account);

        try {
            Mail::to($account->getEmailForPasswordReset())->send(new PasswordResetMail(
                accountName: $this->accountName($account),
                resetUrl: $this->resetUrl($audience, $account->getEmailForPasswordReset(), $token),
                expiresInMinutes: (int) config("auth.passwords.{$this->brokerName($audience)}.expire"),
                audience: $audience,
            ));
        } catch (Throwable $exception) {
            $broker->deleteToken($account);
            throw $exception;
        }
    }

    /** @param array{token: string, password: string} $data */
    public function reset(string $audience, array $data): void
    {
        $credentials = $this->decodePublicToken($data['token']);
        if (! $credentials) {
            throw new ProcessException('Tautan reset password tidak valid atau sudah kedaluwarsa.');
        }

        $account = $this->findActiveAccountByEmail($audience, $credentials['email']);

        if (! $account || ! $this->broker($audience)->tokenExists($account, $credentials['token'])) {
            throw new ProcessException('Tautan reset password tidak valid atau sudah kedaluwarsa.');
        }

        DB::transaction(function () use ($account, $audience, $credentials, $data): void {
            $lockedAccount = $this->findActiveAccountById($audience, (int) $account->getKey());
            $broker = $this->broker($audience);

            if (! $lockedAccount || ! $broker->tokenExists($lockedAccount, $credentials['token'])) {
                throw new ProcessException('Tautan reset password tidak valid atau sudah kedaluwarsa.');
            }

            $lockedAccount->forceFill($this->passwordAttributes($audience, $data['password']))->save();

            AuthRefreshToken::query()
                ->where('refresh_token_owner_type', $audience)
                ->where('refresh_token_owner_id', $lockedAccount->getKey())
                ->whereNull('refresh_token_revoked_at')
                ->update(['refresh_token_revoked_at' => now()]);

            $broker->deleteToken($lockedAccount);
        });
    }

    private function findActiveAccountByEmail(
        string $audience,
        string $email,
    ): SiteAdministrator|MemberAccount|null {
        $normalizedEmail = mb_strtolower(trim($email));

        if ($audience === self::ADMIN) {
            return SiteAdministrator::query()
                ->with('group')
                ->whereRaw('LOWER(administrator_email) = ?', [$normalizedEmail])
                ->where('administrator_is_active', 1)
                ->whereHas('group', fn (Builder $query): Builder => $query->where('administrator_group_is_active', 1))
                ->first();
        }

        $this->assertAudience($audience);

        return MemberAccount::query()
            ->with(['member.level', 'group'])
            ->whereHas('member', fn (Builder $query): Builder => $query
                ->whereRaw('LOWER(member_email) = ?', [$normalizedEmail])
                ->where('member_status', 1)
                ->whereHas('level', fn (Builder $levelQuery): Builder => $levelQuery
                    ->where('member_level_is_active', 1)))
            ->whereHas('group', fn (Builder $query): Builder => $query->where('member_group_is_active', 1))
            ->first();
    }

    private function findActiveMemberAccountByIdentifier(string $identifier): ?MemberAccount
    {
        $normalizedIdentifier = mb_strtolower(trim($identifier));

        return MemberAccount::query()
            ->with(['member.level', 'group'])
            ->whereHas('member', fn (Builder $query): Builder => $query
                ->where(function (Builder $identifierQuery) use ($normalizedIdentifier): void {
                    $identifierQuery
                        ->whereRaw('LOWER(member_email) = ?', [$normalizedIdentifier])
                        ->orWhereRaw('LOWER(member_code) = ?', [$normalizedIdentifier]);
                })
                ->where('member_status', 1)
                ->whereHas('level', fn (Builder $levelQuery): Builder => $levelQuery
                    ->where('member_level_is_active', 1)))
            ->whereHas('group', fn (Builder $query): Builder => $query->where('member_group_is_active', 1))
            ->first();
    }

    private function findActiveAdminAccountByIdentifier(string $identifier): ?SiteAdministrator
    {
        $normalizedIdentifier = mb_strtolower(trim($identifier));

        return SiteAdministrator::query()
            ->with('group')
            ->where(function (Builder $query) use ($normalizedIdentifier): void {
                $query
                    ->whereRaw('LOWER(administrator_email) = ?', [$normalizedIdentifier])
                    ->orWhereRaw('LOWER(administrator_username) = ?', [$normalizedIdentifier]);
            })
            ->where('administrator_is_active', 1)
            ->whereHas('group', fn (Builder $query): Builder => $query->where('administrator_group_is_active', 1))
            ->first();
    }

    private function findActiveAccountById(
        string $audience,
        int $accountId,
    ): SiteAdministrator|MemberAccount|null {
        if ($audience === self::ADMIN) {
            return SiteAdministrator::query()
                ->with('group')
                ->whereKey($accountId)
                ->where('administrator_is_active', 1)
                ->whereHas('group', fn (Builder $query): Builder => $query->where('administrator_group_is_active', 1))
                ->lockForUpdate()
                ->first();
        }

        $this->assertAudience($audience);

        return MemberAccount::query()
            ->with(['member.level', 'group'])
            ->whereKey($accountId)
            ->whereHas('member', fn (Builder $query): Builder => $query
                ->where('member_status', 1)
                ->whereHas('level', fn (Builder $levelQuery): Builder => $levelQuery
                    ->where('member_level_is_active', 1)))
            ->whereHas('group', fn (Builder $query): Builder => $query->where('member_group_is_active', 1))
            ->lockForUpdate()
            ->first();
    }

    private function broker(string $audience): PasswordBroker
    {
        return Password::broker($this->brokerName($audience));
    }

    private function brokerName(string $audience): string
    {
        return match ($audience) {
            self::ADMIN => 'administrators',
            self::MEMBER => 'members',
            default => throw new InvalidArgumentException('Audience reset password tidak didukung.'),
        };
    }

    private function accountName(SiteAdministrator|MemberAccount $account): string
    {
        return $account instanceof SiteAdministrator
            ? $account->administrator_name
            : (string) $account->member?->member_name;
    }

    private function resetUrl(string $audience, string $email, string $token): string
    {
        $baseUrl = rtrim((string) config("dny_auth.password_reset_urls.{$audience}"), '?&');
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.http_build_query([
            'token' => $this->encodePublicToken($email, $token),
        ], encoding_type: PHP_QUERY_RFC3986);
    }

    private function encodePublicToken(string $email, string $token): string
    {
        $encodedEmail = rtrim(strtr(base64_encode(mb_strtolower(trim($email))), '+/', '-_'), '=');

        return $encodedEmail.'.'.$token;
    }

    /** @return array{email: string, token: string}|null */
    private function decodePublicToken(string $publicToken): ?array
    {
        [$encodedEmail, $token] = array_pad(explode('.', trim($publicToken), 2), 2, null);
        if (! is_string($encodedEmail) || $encodedEmail === '' || ! is_string($token) || $token === '') {
            return null;
        }

        $base64 = strtr($encodedEmail, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding !== 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        $email = base64_decode($base64, true);
        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return [
            'email' => mb_strtolower(trim($email)),
            'token' => $token,
        ];
    }

    /** @return array<string, mixed> */
    private function passwordAttributes(string $audience, string $password): array
    {
        if ($audience === self::ADMIN) {
            return [
                'administrator_password' => Hash::make($password),
                'administrator_failed_login_attempts' => 0,
                'administrator_last_failed_login_datetime' => null,
                'administrator_locked_until' => null,
            ];
        }

        $this->assertAudience($audience);

        return [
            'member_account_password' => Hash::make($password),
            'member_account_failed_login_attempts' => 0,
            'member_account_last_failed_login_datetime' => null,
            'member_account_locked_until' => null,
        ];
    }

    private function assertAudience(string $audience): void
    {
        if (! in_array($audience, [self::ADMIN, self::MEMBER], true)) {
            throw new InvalidArgumentException('Audience reset password tidak didukung.');
        }
    }
}
