<?php

namespace App\Http\Resources\Api\V1\Admin\Reward;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminSharingProfitHistoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'paid_datetime' => $this->paid_datetime,
            'trx_code' => $this->trx_code,
            'mitra_name' => $this->mitra_name,
            'trx_price' => (int) $this->trx_price,
            'amount' => (int) $this->amount,
            'receipt_url' => $this->when(
                property_exists($this->resource, 'receipt_url'),
                fn (): ?string => MediaUrl::temporaryPrivateUrl($this->receipt_url, 'admin'),
            ),
            'note' => $this->note,
            'status' => $this->status,
        ];
    }
}
