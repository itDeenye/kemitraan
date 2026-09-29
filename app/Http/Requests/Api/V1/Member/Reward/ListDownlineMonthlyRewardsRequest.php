<?php

namespace App\Http\Requests\Api\V1\Member\Reward;

use App\Models\MemberAccount;
use Illuminate\Foundation\Http\FormRequest;

class ListDownlineMonthlyRewardsRequest extends FormRequest
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
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'is_processed' => ['nullable', 'boolean'],
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
            'year' => 'tahun',
            'month' => 'bulan',
            'is_processed' => 'status pembayaran',
            'search' => 'pencarian',
            'field_search' => 'kolom pencarian',
            'sort' => 'pengurutan',
            'page' => 'halaman',
            'limit' => 'jumlah data',
        ];
    }
}
