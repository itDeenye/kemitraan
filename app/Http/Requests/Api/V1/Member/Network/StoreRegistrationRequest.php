<?php

namespace App\Http\Requests\Api\V1\Member\Network;

use App\Http\Requests\Api\V1\Concerns\HasMemberRegistrationRules;
use App\Models\MemberAccount;
use Illuminate\Foundation\Http\FormRequest;

class StoreRegistrationRequest extends FormRequest
{
    use HasMemberRegistrationRules;

    public function authorize(): bool
    {
        $account = $this->user();

        if (! $account instanceof MemberAccount) {
            return false;
        }

        $account->loadMissing('member.level');

        return in_array($account->member?->level?->member_level_code, ['DST', 'AGT'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = $this->registrationRules();
        unset($rules['identity_image_url']);

        return [
            'level_id' => ['prohibited'],
            ...$rules,
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            ...$this->registrationValidators(),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['level_id' => 'tingkat mitra', ...$this->registrationAttributes()];
    }
}
