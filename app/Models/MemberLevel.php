<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberLevel extends Model
{
    protected $table = 'member_level';

    protected $primaryKey = 'member_level_id';

    public $timestamps = false;

    protected $fillable = [
        'member_level_code',
        'member_level_name',
        'member_level_description',
        'member_level_min_order',
        'member_level_point_value',
        'member_level_sort_order',
        'member_level_is_active',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'member_member_level_id', 'member_level_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(MemberRegistration::class, 'member_registration_member_level_id', 'member_level_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'product_price_member_level_id', 'member_level_id');
    }

    protected function casts(): array
    {
        return [
            'member_level_min_order' => 'integer',
            'member_level_point_value' => 'integer',
            'member_level_sort_order' => 'integer',
            'member_level_is_active' => 'boolean',
        ];
    }
}
