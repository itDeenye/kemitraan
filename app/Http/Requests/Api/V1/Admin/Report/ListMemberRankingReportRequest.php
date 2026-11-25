<?php

namespace App\Http\Requests\Api\V1\Admin\Report;

use App\Http\Requests\Api\V1\Admin\Concerns\HasDataTableRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMemberRankingReportRequest extends FormRequest
{
    use HasDataTableRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->dataTableRules(),
            'report' => ['required', Rule::in(['purchases', 'recruitment', 'monthly-rewards', 'annual-points'])],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['exclude_if:report,annual-points', 'required', 'integer', 'between:1,12'],
        ];
    }
}
