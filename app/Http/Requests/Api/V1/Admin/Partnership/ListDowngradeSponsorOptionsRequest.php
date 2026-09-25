<?php

namespace App\Http\Requests\Api\V1\Admin\Partnership;

use App\Models\Member;
use App\Models\MemberLevel;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDowngradeSponsorOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $memberTable = (new Member)->getTable();
        $levelTable = (new MemberLevel)->getTable();

        return [
            'member_id' => [
                'required',
                'integer',
                Rule::exists($memberTable, 'member_id')
                    ->where(fn (Builder $query): Builder => $query->where('member_status', 1)),
            ],
            'to_level_id' => [
                'required',
                'integer',
                Rule::exists($levelTable, 'member_level_id')
                    ->where(fn (Builder $query): Builder => $query->where('member_level_is_active', 1)),
            ],
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'between:1,50'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'member_id' => 'mitra yang akan di-downgrade',
            'to_level_id' => 'tingkat tujuan',
            'search' => 'pencarian sponsor',
            'limit' => 'jumlah opsi sponsor',
        ];
    }
}
