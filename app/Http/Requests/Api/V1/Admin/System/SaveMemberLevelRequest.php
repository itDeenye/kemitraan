<?php

namespace App\Http\Requests\Api\V1\Admin\System;

use App\Models\MemberLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMemberLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique((new MemberLevel)->getTable(), 'member_level_name')
                    ->ignore($this->route('memberLevel')),
            ],
            'description' => ['nullable', 'string'],
            'min_order' => ['required', 'integer', 'min:0'],
            'point_value' => ['required', 'integer', 'min:0'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'code' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nama tingkat mitra',
            'description' => 'deskripsi',
            'min_order' => 'minimum pembelian',
            'point_value' => 'nilai satu poin',
            'sort_order' => 'urutan',
            'is_active' => 'status aktif',
            'code' => 'kode tingkat',
        ];
    }
}
