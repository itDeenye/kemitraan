<?php

namespace App\Http\Requests\Api\V1\Admin\Partnership;

use App\Models\MemberLevel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMembersRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'field_search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'filter' => ['nullable', 'array', 'max:50'],
            'filter.*' => ['nullable'],
            'filter.level' => ['nullable', 'string', 'max:100'],
            'filter.level_id' => ['nullable', 'integer', Rule::exists((new MemberLevel)->getTable(), 'member_level_id')],
            'filter.status' => ['nullable', 'integer', 'between:0,2'],
        ];
    }
}
