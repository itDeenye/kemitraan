<?php

namespace App\Http\Requests\Api\V1\Admin\Reward;

use App\Http\Requests\Api\V1\Admin\Concerns\HasDataTableRules;
use App\Models\Member;
use App\Models\MemberLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAnnualRewardsRequest extends FormRequest
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
            'filter.member_id' => [
                'nullable',
                'integer',
                Rule::exists((new Member)->getTable(), 'member_id'),
            ],
            'filter.member_level_id' => [
                'nullable',
                'integer',
                Rule::exists((new MemberLevel)->getTable(), 'member_level_id'),
            ],
        ];
    }
}
