<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: ref_district
 */
class RefDistrict extends Model
{
    protected $table = 'ref_district';

    protected $primaryKey = 'district_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];
}