<?php

namespace App\Http\Requests\Api\V1\Admin\System;

use App\Models\SiteAdministratorMenu;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMenuRequest extends FormRequest
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
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists((new SiteAdministratorMenu)->getTable(), 'administrator_menu_id'),
                Rule::notIn(array_filter([$this->route('menu')?->getKey()])),
            ],
            'title' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'link' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'class' => ['nullable', 'string', 'max:255'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ((int) $this->input('parent_id') === 0) {
            $this->merge(['parent_id' => null]);
        }
    }
}
