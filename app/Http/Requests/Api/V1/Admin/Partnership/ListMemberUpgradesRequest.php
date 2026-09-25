<?php

namespace App\Http\Requests\Api\V1\Admin\Partnership;

use App\Http\Requests\Api\V1\Admin\Concerns\HasDataTableRules;
use Illuminate\Foundation\Http\FormRequest;

class ListMemberUpgradesRequest extends FormRequest
{
    use HasDataTableRules;

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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->dataTableRules();
    }
}
