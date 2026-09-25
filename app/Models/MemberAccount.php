<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class MemberAccount extends Authenticatable implements JWTSubject
{
    protected $table = 'member_account';

    protected $primaryKey = 'member_account_id';

    public $timestamps = false;

    protected $fillable = [
        'member_account_member_id',
        'member_account_member_group_id',
        'member_account_username',
        'member_account_password',
        'member_account_pin',
    ];

    protected $hidden = [
        'member_account_password',
        'member_account_pin',
    ];

    public function getAuthPasswordName(): string
    {
        return 'member_account_password';
    }

    public function getEmailForPasswordReset(): string
    {
        return (string) $this->member?->member_email;
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /** @return array<string, string> */
    public function getJWTCustomClaims(): array
    {
        return ['account_type' => 'member'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_account_member_id', 'member_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MemberGroup::class, 'member_account_member_group_id', 'member_group_id');
    }

    protected function casts(): array
    {
        return [
            'member_account_last_login_datetime' => 'datetime',
            'member_account_last_failed_login_datetime' => 'datetime',
            'member_account_locked_until' => 'datetime',
        ];
    }
}
