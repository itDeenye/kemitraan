<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: notification
 */
class Notification extends Model
{
    protected $table = 'notification';

    protected $primaryKey = 'notification_id';

    public $timestamps = false;

    protected $guarded = [];
}