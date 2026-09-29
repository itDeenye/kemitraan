<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: ref_country
 */
class RefCountry extends Model
{
    protected $table = 'ref_country';

    public $incrementing = false;
    protected $primaryKey = null;

    public $timestamps = false;

    protected $guarded = [];
}