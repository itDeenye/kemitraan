<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: bank_company
 */
class BankCompany extends Model
{
    protected $table = 'bank_company';

    protected $primaryKey = 'bank_company_id';

    public $timestamps = false;

    protected $fillable = [
        'bank_company_type',
        'bank_company_bank_id',
        'bank_company_bank_acc_name',
        'bank_company_bank_acc_number',
        'bank_company_bank_is_active',
    ];

    protected function casts(): array
    {
        return ['bank_company_bank_is_active' => 'boolean'];
    }
}
