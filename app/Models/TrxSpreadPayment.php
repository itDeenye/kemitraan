<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrxSpreadPayment extends Model
{
    protected $table = 'trx_spread_payment';

    protected $primaryKey = 'trx_spread_payment_id';

    public $timestamps = false;

    protected $fillable = [
        'trx_spread_payment_trx_id',
        'trx_spread_payment_upline_id',
        'trx_spread_payment_member_id',
        'trx_spread_payment_bank_id',
        'trx_spread_payment_account_name',
        'trx_spread_payment_account_number',
        'trx_spread_payment_percentage',
        'trx_spread_payment_amount',
        'trx_spread_payment_receipt_file',
        'trx_spread_payment_transfer_datetime',
        'trx_spread_payment_status',
        'trx_spread_payment_approved_by',
        'trx_spread_payment_approved_datetime',
        'trx_spread_payment_paid_by',
        'trx_spread_payment_paid_datetime',
        'trx_spread_payment_note',
        'trx_spread_payment_created_datetime',
    ];

    public function trx(): BelongsTo
    {
        return $this->belongsTo(Trx::class, 'trx_spread_payment_trx_id', 'trx_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(RefBank::class, 'trx_spread_payment_bank_id', 'bank_id');
    }

    public function upline(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'trx_spread_payment_upline_id', 'member_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'trx_spread_payment_member_id', 'member_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(SiteAdministrator::class, 'trx_spread_payment_approved_by', 'administrator_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(SiteAdministrator::class, 'trx_spread_payment_paid_by', 'administrator_id');
    }

    protected function casts(): array
    {
        return [
            'trx_spread_payment_percentage' => 'decimal:4',
            'trx_spread_payment_amount' => 'integer',
            'trx_spread_payment_transfer_datetime' => 'datetime',
            'trx_spread_payment_approved_datetime' => 'datetime',
            'trx_spread_payment_paid_datetime' => 'datetime',
            'trx_spread_payment_created_datetime' => 'datetime',
        ];
    }
}
