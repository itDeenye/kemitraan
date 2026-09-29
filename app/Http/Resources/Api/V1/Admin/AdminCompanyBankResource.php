<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class AdminCompanyBankResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'bank' => [
                'id' => $this->bank_id,
                'code' => $this->bank_code,
                'name' => $this->bank_name,
                'logo' => $this->bank_logo,
            ],
            'account_name' => $this->account_name,
            'account_number' => $this->account_number,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
