<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model untuk tabel: trx_payment_transfer
 */
class TrxPaymentTransfer extends Model
{
    protected $table = 'trx_payment_transfer';

    protected $primaryKey = 'payment_transfer_id';

    public $timestamps = false;

    protected $fillable = [
        'payment_transfer_trx_id',
        'payment_transfer_bill_remaining',
        'payment_transfer_bill_augment',
        'payment_transfer_bill_amount',
        'payment_transfer_bank_id',
        'payment_transfer_account_name',
        'payment_transfer_account_number',
        'payment_transfer_amount',
        'payment_transfer_datetime',
        'payment_transfer_receipt_file',
        'payment_transfer_approval_status',
        'payment_transfer_approval_admin_id',
        'payment_transfer_approval_datetime',
        'payment_transfer_note',
    ];

    public function trx(): BelongsTo
    {
        return $this->belongsTo(Trx::class, 'payment_transfer_trx_id', 'trx_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(RefBank::class, 'payment_transfer_bank_id', 'bank_id');
    }

    protected function casts(): array
    {
        return [
            'payment_transfer_bill_remaining' => 'integer',
            'payment_transfer_bill_augment' => 'integer',
            'payment_transfer_bill_amount' => 'integer',
            'payment_transfer_amount' => 'integer',
            'payment_transfer_datetime' => 'datetime',
            'payment_transfer_approval_datetime' => 'datetime',
        ];
    }
}
