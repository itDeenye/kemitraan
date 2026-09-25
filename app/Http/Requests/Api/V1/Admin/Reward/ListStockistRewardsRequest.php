<?php

namespace App\Http\Requests\Api\V1\Admin\Reward;

use App\Http\Requests\Api\V1\Admin\Concerns\HasDataTableRules;
use Illuminate\Foundation\Http\FormRequest;

class ListStockistRewardsRequest extends FormRequest
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
            'filter.year' => ['nullable', 'integer', 'between:2020,2100'],
            'filter.month' => ['nullable', 'integer', 'between:1,12'],
            'filter.member_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
