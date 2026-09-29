<?php

namespace App\Http\Requests\Api\V1\Member\Reward;

use Illuminate\Foundation\Http\FormRequest;

class ListStockistRewardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'search' => ['nullable', 'string'],
        ];
    }
}
