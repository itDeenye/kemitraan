<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    public const SCOPE_SYSTEM = 'system';

    public const SCOPE_COMMISSION = 'commission';

    protected $table = 'config';

    protected $primaryKey = 'config_id';

    public $timestamps = false;

    protected $fillable = [
        'config_key',
        'config_value',
        'config_type',
        'config_scope',
        'config_created_datetime',
        'config_updated_datetime',
    ];

    protected $attributes = [
        'config_scope' => self::SCOPE_SYSTEM,
    ];

    protected function casts(): array
    {
        return [
            'config_created_datetime' => 'datetime',
            'config_updated_datetime' => 'datetime',
        ];
    }
}
