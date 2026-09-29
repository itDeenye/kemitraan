<?php

namespace App\Http\Requests\Api\V1\Admin\Partnership;

use App\Http\Requests\Api\V1\Concerns\HasMemberRegistrationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreDistributorRegistrationRequest extends FormRequest
{
    use HasMemberRegistrationRules;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'level_id' => ['prohibited'],
            ...$this->registrationRules(),
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return $this->registrationValidators();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['level_id' => 'tingkat mitra', ...$this->registrationAttributes()];
    }
}
