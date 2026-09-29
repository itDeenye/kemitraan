<?php

namespace App\Http\Requests\Api\V1\Admin\Partnership;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeactivateMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'replacement_sponsor_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('member', 'member_id')->where('member_status', 1),
            ],
            'cancel_active_transactions' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'replacement_sponsor_id' => 'sponsor pengganti',
            'cancel_active_transactions' => 'konfirmasi pembatalan transaksi aktif',
            'note' => 'catatan penonaktifan',
        ];
    }
}
