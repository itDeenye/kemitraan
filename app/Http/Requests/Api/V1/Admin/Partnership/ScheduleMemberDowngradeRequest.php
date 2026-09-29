<?php

namespace App\Http\Requests\Api\V1\Admin\Partnership;

use App\Models\MemberLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleMemberDowngradeRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $levelTable = (new MemberLevel)->getTable();

        return [
            'to_level_id' => [
                'required',
                'integer',
                Rule::exists($levelTable, 'member_level_id')
                    ->where('member_level_is_active', 1),
            ],
            'to_parent_member_id' => ['required', 'integer', 'min:1', 'exists:member,member_id'],
            'note' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'to_level_id' => 'tingkat tujuan',
            'to_parent_member_id' => 'sponsor baru',
            'note' => 'alasan downgrade',
        ];
    }
}
