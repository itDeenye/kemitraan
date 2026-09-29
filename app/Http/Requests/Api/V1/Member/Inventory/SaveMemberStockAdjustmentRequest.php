<?php

namespace App\Http\Requests\Api\V1\Member\Inventory;

use App\Models\MemberAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMemberStockAdjustmentRequest extends FormRequest
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

    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('product', 'product_id')->where('product_is_deleted', 0),
            ],
            'qty' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'product_id' => 'Produk',
            'qty' => 'Jumlah Penyesuaian',
            'reason' => 'Alasan Penyesuaian',
        ];
    }
}
