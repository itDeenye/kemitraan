<?php

namespace App\Http\Requests\Api\V1\Admin\Partnership;

use App\Http\Requests\Api\V1\Concerns\HasMemberRegistrationRules;
use App\Models\MemberRegistration;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRegistrationRequest extends FormRequest
{
    use HasMemberRegistrationRules {
        prepareForValidation as private prepareRegistrationForValidation;
    }

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
        $rules = $this->registrationRules();
        foreach ($rules as $field => &$fieldRules) {
            if (! str_contains($field, '.') && in_array('required', $fieldRules, true)) {
                array_unshift($fieldRules, 'sometimes');
            }
        }
        unset($fieldRules);
        foreach (['sponsor_id', 'status', 'member_id', 'customer_number', 'code',
            'distributor_approval_status', 'submitted_by_id', 'note'] as $field) {
            $rules[$field] = ['prohibited'];
        }
        $rules['bank_accounts'] = ['sometimes', 'array', 'max:10'];
        $rules['level_id'] = ['sometimes', 'required', 'integer', 'min:1'];
        $rules['upline_member_id'] = ['sometimes', 'nullable', 'integer', 'min:0'];

        return $rules;
    }

    public function after(): array
    {
        $registration = $this->route('registration');

        return $this->registrationValidators($registration instanceof MemberRegistration ? $registration : null);
    }

    public function attributes(): array
    {
        return ['level_id' => 'level', 'upline_member_id' => 'upline', ...$this->registrationAttributes()];
    }

    protected function prepareForValidation(): void
    {
        $registration = $this->route('registration');
        if ($registration instanceof MemberRegistration && $this->hasAny(['bank_id', 'bank_account_name', 'bank_account_number', 'bank_city', 'bank_branch'])) {
            $this->merge([
                'bank_id' => $registration->member_registration_bank_id ?: null,
                'bank_account_name' => $registration->member_registration_bank_account_name,
                'bank_account_number' => $registration->member_registration_bank_account_no,
                'bank_city' => $registration->member_registration_bank_city,
                'bank_branch' => $registration->member_registration_bank_branch,
                ...$this->all(),
            ]);
        }
        $this->prepareRegistrationForValidation();
    }
}
