<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: ref_city
 */
class RefCity extends Model
{
    protected $table = 'ref_city';

    protected $primaryKey = 'city_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];
}