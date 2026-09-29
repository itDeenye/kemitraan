<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthRefreshToken extends Model
{
    protected $table = 'auth_refresh_token';

    protected $primaryKey = 'refresh_token_id';

    public $timestamps = false;

    protected $fillable = [
        'refresh_token_owner_type',
        'refresh_token_owner_id',
        'refresh_token_hash',
        'refresh_token_device_name',
        'refresh_token_expires_at',
        'refresh_token_last_used_at',
        'refresh_token_revoked_at',
        'refresh_token_created_at',
    ];

    protected $hidden = [
        'refresh_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'refresh_token_expires_at' => 'datetime',
            'refresh_token_last_used_at' => 'datetime',
            'refresh_token_revoked_at' => 'datetime',
            'refresh_token_created_at' => 'datetime',
        ];
    }
}
