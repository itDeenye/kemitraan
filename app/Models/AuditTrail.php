<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditTrail extends Model
{
    protected $table = 'audittrail';

    protected $primaryKey = 'audittrail_id';

    public $timestamps = false;

    protected $guarded = ['audittrail_id'];

    protected function casts(): array
    {
        return [
            'audittrail_datetime' => 'datetime',
        ];
    }
}
