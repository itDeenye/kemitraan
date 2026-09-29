<?php

namespace App\Http\Requests\Api\V1\Admin\System;

use App\Models\SiteAdministratorMenu;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRolePrivilegesRequest extends FormRequest
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
            'menus' => ['present', 'array'],
            'menus.*' => ['required', 'array:menu_id'],
            'menus.*.menu_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists((new SiteAdministratorMenu)->getTable(), 'administrator_menu_id'),
            ],
        ];
    }
}
