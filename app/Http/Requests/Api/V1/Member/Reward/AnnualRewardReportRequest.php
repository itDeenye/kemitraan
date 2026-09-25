<?php

namespace App\Http\Requests\Api\V1\Member\Reward;

use Illuminate\Foundation\Http\FormRequest;

class AnnualRewardReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'between:2020,2100'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['year' => 'tahun'];
    }
}
