<?php

namespace App\Http\Requests\Api\V1\Member\Return;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShipMemberReturnRequest extends FormRequest
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
            'pickup_schedule' => ['required', 'date', 'after:now'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'pickup_schedule' => 'jadwal pengiriman retur',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'pickup_schedule.after' => 'Jadwal pengiriman retur harus setelah waktu saat ini.',
        ];
    }
}
