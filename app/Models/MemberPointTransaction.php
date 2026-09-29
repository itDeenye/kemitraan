<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberPointTransaction extends Model
{
    protected $table = 'member_point_transaction';

    protected $primaryKey = 'member_point_transaction_id';

    public $timestamps = false;

    protected $fillable = [
        'member_point_transaction_member_id',
        'member_point_transaction_trx_id',
        'member_point_transaction_quantity',
        'member_point_transaction_year',
        'member_point_transaction_month',
        'member_point_transaction_approved_datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_point_transaction_member_id', 'member_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Trx::class, 'member_point_transaction_trx_id', 'trx_id');
    }

    protected function casts(): array
    {
        return [
            'member_point_transaction_quantity' => 'integer',
            'member_point_transaction_year' => 'integer',
            'member_point_transaction_month' => 'integer',
            'member_point_transaction_approved_datetime' => 'datetime',
        ];
    }
}
