<?php

namespace App\Http\Requests\Api\V1\Member\Network;

use App\Models\MemberAccount;
use Illuminate\Foundation\Http\FormRequest;

class ApproveRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->user();
        if (! $account instanceof MemberAccount) {
            return false;
        }
        $account->loadMissing('member.level');

        return $account->member?->level?->member_level_code === 'DST';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['note' => 'catatan persetujuan'];
    }
}
