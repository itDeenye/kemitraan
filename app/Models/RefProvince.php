<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: ref_province
 */
class RefProvince extends Model
{
    protected $table = 'ref_province';

    protected $primaryKey = 'province_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];
}