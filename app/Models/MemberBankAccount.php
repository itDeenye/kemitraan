<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberBankAccount extends Model
{
    protected $table = 'member_bank_account';

    protected $primaryKey = 'member_bank_account_id';

    public $timestamps = false;

    protected $appends = [
        'bank_code',
        'bank_name',
        'is_default',
    ];

    protected $hidden = ['bank'];

    protected $fillable = [
        'member_bank_account_member_id',
        'member_bank_account_bank_id',
        'member_bank_account_name',
        'member_bank_account_number',
        'member_bank_account_city',
        'member_bank_account_branch',
        'member_bank_account_is_active',
        'member_bank_account_is_default',
    ];

    protected function casts(): array
    {
        return [
            'member_bank_account_is_active' => 'boolean',
            'member_bank_account_is_default' => 'boolean',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(RefBank::class, 'member_bank_account_bank_id', 'bank_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_bank_account_member_id', 'member_id');
    }

    public function getIsDefaultAttribute(): bool
    {
        return (bool) $this->member_bank_account_is_default;
    }

    public function getBankCodeAttribute(): ?string
    {
        return $this->bank?->bank_code;
    }

    public function getBankNameAttribute(): ?string
    {
        return $this->bank?->bank_name;
    }
}
