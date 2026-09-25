<?php

namespace App\Http\Requests\Api\V1\Admin\Company;

use App\Models\BankCompany;
use App\Models\RefBank;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCompanyBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['company', 'spread_payment'])],
            'bank_id' => [
                'required',
                'integer',
                Rule::exists((new RefBank)->getTable(), 'bank_id'),
            ],
            'account_name' => ['required', 'string', 'max:50'],
            'account_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique((new BankCompany)->getTable(), 'bank_company_bank_acc_number')
                    ->where('bank_company_bank_id', $this->input('bank_id'))
                    ->ignore($this->route('companyBank')),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'type' => 'tipe rekening',
            'bank_id' => 'bank',
            'account_name' => 'nama pemilik rekening',
            'account_number' => 'nomor rekening',
            'is_active' => 'status aktif',
        ];
    }
}
