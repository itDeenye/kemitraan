<?php

namespace App\Http\Requests\Api\V1\Member\Network;

use App\Models\MemberAccount;
use Illuminate\Foundation\Http\FormRequest;

class ListRegistrationsRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'field_search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'filter' => ['nullable', 'array'],
        ];
    }
}
