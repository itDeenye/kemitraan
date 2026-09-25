<?php

namespace App\Http\Requests\Api\V1\Admin\Reward;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferSharingProfitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'upline_ids' => ['required', 'array', 'min:1'],
            'upline_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('member', 'member_id'),
            ],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'upline_ids' => 'daftar mitra penerima',
            'upline_ids.*' => 'mitra penerima',
            'note' => 'catatan transfer',
        ];
    }
}
