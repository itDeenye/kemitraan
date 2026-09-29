<?php

namespace App\Http\Requests\Api\V1\Admin\Partnership;

use Illuminate\Foundation\Http\FormRequest;

class ProcessMemberDowngradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['note' => ['nullable', 'string', 'max:500']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['note' => 'catatan keputusan'];
    }
}
