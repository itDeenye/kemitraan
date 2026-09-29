<?php

namespace App\Http\Requests\Api\V1\Member\Inventory;

use App\Models\MemberAccount;
use Illuminate\Foundation\Http\FormRequest;

class ViewMemberStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->user();

        if (! $account instanceof MemberAccount) {
            return false;
        }

        $account->loadMissing('member.level');

        return in_array($account->member?->level?->member_level_code, ['AGT', 'RSL'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
