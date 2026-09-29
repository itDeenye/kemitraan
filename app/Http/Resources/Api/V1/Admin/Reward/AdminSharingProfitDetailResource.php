<?php

namespace App\Http\Resources\Api\V1\Admin\Reward;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminSharingProfitDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'submitted_datetime' => $this->submitted_datetime,
            'approved_datetime' => $this->approved_datetime,
            'paid_datetime' => $this->paid_datetime,
            'trx_code' => $this->trx_code,
            'buyer_name' => $this->buyer_name,
            'trx_price' => (int) $this->trx_price,
            'amount' => (int) $this->amount,
            'receipt_url' => MediaUrl::temporaryPrivateUrl($this->receipt_url, 'admin'),
            'note' => $this->note,
            'status' => $this->status,
        ];
    }
}
