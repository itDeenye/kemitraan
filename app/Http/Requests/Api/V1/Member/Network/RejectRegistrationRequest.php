<?php

namespace App\Http\Requests\Api\V1\Member\Network;

class RejectRegistrationRequest extends ApproveRegistrationRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['note' => 'alasan penolakan'];
    }
}
