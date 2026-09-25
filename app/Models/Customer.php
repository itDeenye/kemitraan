<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model untuk tabel: customer
 */
class Customer extends Model
{
    protected $table = 'customer';

    protected $primaryKey = 'customer_id';

    public $timestamps = false;

    protected $fillable = [
        'customer_member_id',
        'customer_name',
        'customer_whatsapp',
        'customer_phone',
        'customer_gender',
        'customer_birth_date',
        'customer_address',
        'customer_subdistrict_id',
        'customer_district_id',
        'customer_city_id',
        'customer_province_id',
        'customer_is_deleted',
        'customer_created_datetime',
    ];

    protected function casts(): array
    {
        return [
            'customer_birth_date' => 'date',
            'customer_is_deleted' => 'boolean',
            'customer_created_datetime' => 'datetime',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(RefProvince::class, 'customer_province_id', 'province_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(RefCity::class, 'customer_city_id', 'city_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(RefDistrict::class, 'customer_district_id', 'district_id');
    }

    public function subdistrict(): BelongsTo
    {
        return $this->belongsTo(RefSubdistrict::class, 'customer_subdistrict_id', 'subdistrict_id');
    }
}
