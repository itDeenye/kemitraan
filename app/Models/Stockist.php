<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stockist extends Model
{
    protected $table = 'stockist';

    protected $primaryKey = 'stockist_id';

    public $timestamps = false;

    protected $fillable = [
        'stockist_member_id',
        'stockist_name',
        'stockist_email',
        'stockist_address',
        'stockist_mobilephone',
        'stockist_image',
        'stockist_subdistrict_id',
        'stockist_district_id',
        'stockist_city_id',
        'stockist_province_id',
        'stockist_latitude',
        'stockist_longitude',
        'stockist_note',
        'stockist_is_active',
        'stockist_is_deleted',
        'stockist_input_datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'stockist_member_id', 'member_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(RefProvince::class, 'stockist_province_id', 'province_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(RefCity::class, 'stockist_city_id', 'city_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(RefDistrict::class, 'stockist_district_id', 'district_id');
    }

    public function subdistrict(): BelongsTo
    {
        return $this->belongsTo(RefSubdistrict::class, 'stockist_subdistrict_id', 'subdistrict_id');
    }

    protected function casts(): array
    {
        return [
            'stockist_member_id' => 'integer',
            'stockist_is_active' => 'boolean',
            'stockist_is_deleted' => 'boolean',
            'stockist_input_datetime' => 'datetime',
        ];
    }
}
