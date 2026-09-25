<?php

namespace App\Http\Requests\Api\V1\Admin\Company;

use App\Http\Requests\Api\V1\Admin\Concerns\HasDataTableRules;
use Illuminate\Foundation\Http\FormRequest;

class ListCompanyBanksRequest extends FormRequest
{
    use HasDataTableRules;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->dataTableRules(),
            'filter.bank_id' => ['nullable', 'integer', 'min:1'],
            'filter.type' => ['nullable', 'in:company,spread_payment'],
            'filter.is_active' => ['nullable', 'boolean'],
        ];
    }
}
