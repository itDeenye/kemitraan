<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class MemberBankAccountResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->member_bank_account_id,
            'bank_id' => (int) $this->resource->member_bank_account_bank_id,
            'bank_code' => $this->resource->bank?->bank_code,
            'bank_name' => $this->resource->bank?->bank_name,
            'account_name' => $this->resource->member_bank_account_name,
            'account_number' => $this->resource->member_bank_account_number,
            'city' => $this->resource->member_bank_account_city,
            'branch' => $this->resource->member_bank_account_branch,
            'is_active' => (bool) $this->resource->member_bank_account_is_active,
            'is_default' => (bool) $this->resource->member_bank_account_is_default,
        ];
    }
}
