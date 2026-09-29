<?php

namespace App\Http\Requests\Api\V1\Admin\System;

use App\Models\SiteAdministratorGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoleRequest extends FormRequest
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
            'title' => [
                'required',
                'string',
                'max:50',
                Rule::unique((new SiteAdministratorGroup)->getTable(), 'administrator_group_title')
                    ->ignore($this->route('role')),
            ],
            'type' => ['required', 'in:superuser,administrator'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
