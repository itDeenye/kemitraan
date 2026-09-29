<?php

namespace App\Http\Requests\Api\V1\Admin\Reward;

use Illuminate\Foundation\Http\FormRequest;

class ApproveSharingProfitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'upline_ids' => 'required|array|min:1',
            'upline_ids.*' => 'required|integer|exists:member,member_id',
        ];
    }
}
