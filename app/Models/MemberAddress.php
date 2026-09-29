<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberAddress extends Model
{
    protected $table = 'member_address';

    protected $primaryKey = 'member_address_id';

    public $timestamps = false;

    protected $fillable = [
        'member_address_member_id',
        'member_address_label',
        'member_address_recipient',
        'member_address_phone',
        'member_address_full',
        'member_address_subdistrict_id',
        'member_address_district_id',
        'member_address_city_id',
        'member_address_province_id',
        'member_address_country_id',
        'member_address_is_default',
    ];

    protected function casts(): array
    {
        return [
            'member_address_is_default' => 'boolean',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(RefProvince::class, 'member_address_province_id', 'province_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(RefCity::class, 'member_address_city_id', 'city_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(RefDistrict::class, 'member_address_district_id', 'district_id');
    }

    public function subdistrict(): BelongsTo
    {
        return $this->belongsTo(RefSubdistrict::class, 'member_address_subdistrict_id', 'subdistrict_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(RefCountry::class, 'member_address_country_id', 'country_id');
    }
}
