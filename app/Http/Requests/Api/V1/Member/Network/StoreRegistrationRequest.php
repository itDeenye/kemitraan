<?php

namespace App\Http\Requests\Api\V1\Member\Network;

use App\Http\Requests\Api\V1\Concerns\HasMemberRegistrationRules;
use App\Models\MemberAccount;
use App\Models\MemberLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'level_id' => [
                'required',
                'integer',
                Rule::exists((new MemberLevel)->getTable(), 'member_level_id')
                    ->where('member_level_is_active', 1),
            ],
            ...$rules,
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            ...$this->registrationValidators(),
            function (Validator $validator): void {
                $account = $this->user();
                $levelId = $this->integer('level_id');

                if (! $account instanceof MemberAccount || $levelId === 0) {
                    return;
                }

                $account->loadMissing('member.level');
                $targetCode = MemberLevel::query()
                    ->whereKey($levelId)
                    ->value('member_level_code');
                $allowedCodes = match ($account->member?->level?->member_level_code) {
                    'DST' => ['AGT', 'RSL'],
                    'AGT' => ['RSL'],
                    default => [],
                };

                if (! in_array($targetCode, $allowedCodes, true)) {
                    $validator->errors()->add('level_id', 'Tingkat mitra tidak diperbolehkan.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['level_id' => 'tingkat mitra', ...$this->registrationAttributes()];
    }
}
