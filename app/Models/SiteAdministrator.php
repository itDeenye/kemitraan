<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class SiteAdministrator extends Authenticatable implements JWTSubject
{
    protected $table = 'site_administrator';

    protected $primaryKey = 'administrator_id';

    public $timestamps = false;

    protected $fillable = [
        'administrator_administrator_group_id',
        'administrator_username',
        'administrator_password',
        'administrator_name',
        'administrator_email',
        'administrator_mobilephone',
        'administrator_image',
        'administrator_is_active',
    ];

    protected $hidden = [
        'administrator_password',
    ];

    public function getAuthPasswordName(): string
    {
        return 'administrator_password';
    }

    public function getEmailForPasswordReset(): string
    {
        return (string) $this->administrator_email;
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /** @return array<string, string> */
    public function getJWTCustomClaims(): array
    {
        return ['account_type' => 'admin'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(
            SiteAdministratorGroup::class,
            'administrator_administrator_group_id',
            'administrator_group_id',
        );
    }

    protected function casts(): array
    {
        return [
            'administrator_is_active' => 'boolean',
            'administrator_last_login' => 'datetime',
            'administrator_last_failed_login_datetime' => 'datetime',
            'administrator_locked_until' => 'datetime',
        ];
    }
}
