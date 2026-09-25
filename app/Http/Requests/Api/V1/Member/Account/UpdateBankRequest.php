<?php

namespace App\Http\Requests\Api\V1\Member\Account;

use App\Models\RefBank;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_id' => ['nullable', 'integer', Rule::exists((new RefBank)->getTable(), 'bank_id')],
            'account_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:50'],
            'branch' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'bank_id' => 'bank',
            'account_name' => 'nama pemilik rekening',
            'account_number' => 'nomor rekening',
            'city' => 'kota bank',
            'branch' => 'cabang bank',
            'is_active' => 'status rekening',
            'is_default' => 'status rekening default',
        ];
    }
}
