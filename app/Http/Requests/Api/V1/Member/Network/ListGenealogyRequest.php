<?php

namespace App\Http\Requests\Api\V1\Member\Network;

use App\Models\Member;
use App\Models\MemberAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListGenealogyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->user();

        if (! $account instanceof MemberAccount) {
            return false;
        }

        $account->loadMissing('member.level');

        return in_array($account->member?->level?->member_level_code, ['DST', 'AGT'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists((new Member)->getTable(), 'member_id')->whereNot('member_status', 3),
            ],
            'search' => ['nullable', 'string', 'max:100'],
            'field_search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'parent_id' => 'mitra induk',
            'search' => 'pencarian',
            'field_search' => 'kolom pencarian',
            'sort' => 'pengurutan',
            'page' => 'halaman',
            'limit' => 'jumlah data',
        ];
    }
}
