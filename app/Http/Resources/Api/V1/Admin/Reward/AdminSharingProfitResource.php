<?php

namespace App\Http\Resources\Api\V1\Admin\Reward;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminSharingProfitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $submittedCount = (int) $this->submitted_count;
        $approvedCount = (int) $this->approved_count;

        return [
            'upline' => [
                'id' => $this->upline_id,
                'name' => $this->upline_name,
                'code' => $this->upline_code,
            ],
            'total_trx_price' => (int) $this->total_trx_price,
            'total_amount' => (int) $this->total_amount,
            'transaction_count' => (int) $this->transaction_count,
            'submitted_count' => $submittedCount,
            'approved_count' => $approvedCount,
            'status' => $submittedCount > 0 ? 'submitted' : 'approved',
            'can_approve' => $submittedCount > 0,
            'can_transfer' => $submittedCount === 0 && $approvedCount > 0,
        ];
    }
}
