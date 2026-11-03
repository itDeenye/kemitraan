<?php

namespace App\Http\Requests\Api\V1\Admin\Transaction;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class PaymentHistoryRequest extends ListPaymentsRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canAccessMenu('/transactions/payment-verification-history') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'filter.status' => ['nullable', Rule::in(['approved', 'rejected'])],
        ];
    }
}
