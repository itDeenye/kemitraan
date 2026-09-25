<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: ref_subdistrict
 */
class RefSubdistrict extends Model
{
    protected $table = 'ref_subdistrict';

    protected $primaryKey = 'subdistrict_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $guarded = [];
}