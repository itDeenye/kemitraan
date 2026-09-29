<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: ref_bank
 */
class RefBank extends Model
{
    protected $table = 'ref_bank';

    public $incrementing = false;
    protected $primaryKey = null;

    public $timestamps = false;

    protected $guarded = [];
}