<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: bonus_transfer
 */
class BonusTransfer extends Model
{
    protected $table = 'bonus_transfer';

    protected $primaryKey = 'bonus_transfer_id';

    public $timestamps = false;

    protected $guarded = [];
}