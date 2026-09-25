<?php

namespace App\Http\Requests\Api\V1\Member\Reward;

use App\Models\MemberAccount;
use Illuminate\Foundation\Http\FormRequest;

class ApproveDownlineMonthlyRewardRequest extends FormRequest
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
        return [];
    }
}
