<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model untuk tabel: warehouse
 */
class Warehouse extends Model
{
    protected $table = 'warehouse';

    protected $primaryKey = 'warehouse_id';

    public $timestamps = false;

    protected $fillable = [
        'warehouse_name',
        'warehouse_legal_name',
        'warehouse_npwp',
        'warehouse_phone',
        'warehouse_email',
        'warehouse_logo',
        'warehouse_address',
        'warehouse_province_id',
        'warehouse_city_id',
        'warehouse_district_id',
        'warehouse_subdistrict_id',
        'warehouse_latitude',
        'warehouse_longitude',
        'warehouse_is_active',
        'warehouse_created_datetime',
    ];

    protected function casts(): array
    {
        return [
            'warehouse_is_active' => 'boolean',
            'warehouse_created_datetime' => 'datetime',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(RefProvince::class, 'warehouse_province_id', 'province_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(RefCity::class, 'warehouse_city_id', 'city_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(RefDistrict::class, 'warehouse_district_id', 'district_id');
    }

    public function subdistrict(): BelongsTo
    {
        return $this->belongsTo(RefSubdistrict::class, 'warehouse_subdistrict_id', 'subdistrict_id');
    }
}
