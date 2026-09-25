<?php

namespace App\Http\Requests\Api\V1\Member\Inventory;

use App\Models\MemberAccount;
use Illuminate\Foundation\Http\FormRequest;

class ListMemberStockAdjustmentsRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'search' => 'Pencarian',
            'start_date' => 'Tanggal Awal',
            'end_date' => 'Tanggal Akhir',
            'page' => 'Halaman',
            'limit' => 'Jumlah data',
            'sort' => 'Pengurutan',
        ];
    }
}
