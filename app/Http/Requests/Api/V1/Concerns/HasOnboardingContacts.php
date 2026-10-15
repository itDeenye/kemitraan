<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Http\Requests\Api\V1\Member\Account\StoreBankRequest;
use App\Models\RefBank;
use App\Support\MediaUrl;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait HasOnboardingContacts
{
    /** @return array<string, mixed> */
    protected function onboardingContactRules(): array
    {
        $rules = [
            'addresses' => ['prohibited'],
            'bank_accounts' => ['sometimes', 'nullable', 'array', 'max:10'],
            'bank_accounts.*' => ['array'],
            'bank_accounts.*.id' => ['prohibited'],
        ];
        foreach ((new StoreBankRequest)->rules() as $field => $fieldRules) {
            $rules["bank_accounts.*.{$field}"] = $fieldRules;
        }
        $rules['bank_accounts.*.account_name'] = ['required', 'string', 'max:50'];
        $rules['bank_accounts.*.bank_id'] = [
            'required', 'integer',
            Rule::exists((new RefBank)->getTable(), 'bank_id')->where('bank_is_active', 1),
        ];

        return $rules;
    }

    protected function prepareOnboardingContacts(): void
    {
        foreach (['identity_image_url', 'payment_proof_image_url'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => MediaUrl::canonicalPrivateUrl($this->input($field))]);
            }
        }
        if (is_string($this->input('identity_type'))) {
            $this->merge(['identity_type' => strtoupper(trim($this->input('identity_type')))]);
        }
    }

    protected function validateOnboardingContacts(Validator $validator): void
    {
        $banks = is_array($this->input('bank_accounts')) ? $this->input('bank_accounts') : [];
        $defaults = array_filter($banks, fn (mixed $bank): bool => is_array($bank)
            && filter_var($bank['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN));
        if (count($defaults) > 1) {
            $validator->errors()->add('bank_accounts', 'Hanya satu data yang dapat dijadikan utama.');
        }
        foreach ($banks as $index => $bank) {
            if (is_array($bank)
                && filter_var($bank['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN)
                && ! filter_var($bank['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                $validator->errors()->add("bank_accounts.{$index}.is_default", 'Rekening default harus berstatus aktif.');
            }
        }
    }
}
