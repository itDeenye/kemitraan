<?php

namespace App\Http\Requests\Api\V1\Admin\Partnership;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListGenealogyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'member_id' => ['nullable', 'integer', Rule::exists((new Member)->getTable(), 'member_id')->whereNot('member_status', 3)],
            'depth' => ['nullable', 'integer', 'between:1,5'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['member_id' => 'mitra induk', 'depth' => 'kedalaman jaringan'];
    }
}
