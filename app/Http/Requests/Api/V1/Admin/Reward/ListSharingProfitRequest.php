<?php

namespace App\Http\Requests\Api\V1\Admin\Reward;

use Illuminate\Foundation\Http\FormRequest;

class ListSharingProfitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1',
            'sort' => 'nullable|string',
            'search' => 'nullable|string|max:100',
            'pagination_bool' => 'nullable|in:true,false,1,0',
        ];
    }
}
