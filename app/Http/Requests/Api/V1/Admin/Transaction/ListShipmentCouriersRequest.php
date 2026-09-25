<?php

namespace App\Http\Requests\Api\V1\Admin\Transaction;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListShipmentCouriersRequest extends FormRequest
{
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
            'couriers' => ['nullable', 'array', 'max:20'],
            'couriers.*' => ['required', 'string', 'max:30'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'couriers' => 'filter kurir',
            'couriers.*' => 'kode kurir',
        ];
    }
}
