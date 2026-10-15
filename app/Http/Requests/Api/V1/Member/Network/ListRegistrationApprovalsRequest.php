<?php

namespace App\Http\Requests\Api\V1\Member\Network;

class ListRegistrationApprovalsRequest extends ListRegistrationsRequest
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->user()->member->level->member_level_code === 'DST';
    }
}
