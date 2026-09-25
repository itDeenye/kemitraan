<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: member_stock
 */
class MemberStock extends Model
{
    protected $table = 'member_stock';

    protected $primaryKey = 'member_stock_id';

    public $timestamps = false;

    protected $guarded = [];
}