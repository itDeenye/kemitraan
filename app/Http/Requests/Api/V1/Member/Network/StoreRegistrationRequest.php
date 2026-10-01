<?php

namespace App\Http\Requests\Api\V1\Member\Network;

use App\Http\Requests\Api\V1\Concerns\HasMemberRegistrationRules;
use App\Models\Member;
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
            'upline_member_id' => ['nullable', 'integer'],
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
                $member = $account->member;
                $targetCode = MemberLevel::query()
                    ->whereKey($levelId)
                    ->value('member_level_code');
                $memberLevelCode = $member?->level?->member_level_code;
                $allowedCodes = match ($memberLevelCode) {
                    'DST' => ['AGT', 'RSL'],
                    'AGT' => ['RSL'],
                    default => [],
                };

                if (! in_array($targetCode, $allowedCodes, true)) {
                    $validator->errors()->add('level_id', 'Tingkat mitra tidak diperbolehkan.');
                }

                $uplineMemberId = $this->integer('upline_member_id');
                $requiresAgentUpline = $memberLevelCode === 'DST' && $targetCode === 'RSL';

                if (! $requiresAgentUpline) {
                    if ($uplineMemberId !== 0) {
                        $validator->errors()->add(
                            'upline_member_id',
                            'Agen Utama hanya dipilih saat Distributor mendaftarkan Reseller.',
                        );
                    }

                    return;
                }

                if ($uplineMemberId === 0) {
                    $validator->errors()->add(
                        'upline_member_id',
                        'Agen Utama untuk Reseller wajib dipilih.',
                    );

                    return;
                }

                $agentBelongsToDistributor = Member::query()
                    ->whereKey($uplineMemberId)
                    ->where('member_parent_member_id', $member?->getKey())
                    ->where('member_status', 1)
                    ->whereHas('level', fn ($query) => $query
                        ->where('member_level_code', 'AGT')
                        ->where('member_level_is_active', 1))
                    ->exists();

                if (! $agentBelongsToDistributor) {
                    $validator->errors()->add(
                        'upline_member_id',
                        'Agen Utama tidak aktif atau bukan bagian dari jaringan Distributor.',
                    );
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'level_id' => 'tingkat mitra',
            'upline_member_id' => 'Agen Utama',
            ...$this->registrationAttributes(),
        ];
    }
}
