<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class MemberSharingProfitResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $isModel = $this->resource instanceof Model;

        return [
            'id' => (int) ($isModel ? $this->trx_spread_payment_id : $this->id),
            'transaction' => [
                'id' => (int) ($isModel ? $this->trx_spread_payment_trx_id : $this->trx_id),
                'code' => $isModel ? $this->trx?->trx_code : $this->trx_code,
                'amount' => (int) ($isModel ? $this->trx?->trx_grand_total_price : $this->trx_price),
            ],
            'buyer' => [
                'id' => (int) ($isModel ? $this->trx_spread_payment_member_id : $this->buyer_id),
                'code' => $isModel ? $this->member?->member_code : $this->buyer_code,
                'name' => $isModel ? $this->member?->member_name : $this->buyer_name,
            ],
            'percentage' => (float) ($isModel ? $this->trx_spread_payment_percentage : $this->percentage),
            'amount' => (int) ($isModel ? $this->trx_spread_payment_amount : $this->amount),
            'status' => $isModel ? $this->trx_spread_payment_status : $this->status,
            'bank' => [
                'id' => (int) ($isModel ? $this->trx_spread_payment_bank_id : $this->bank_id),
                'account_name' => $isModel
                    ? $this->trx_spread_payment_account_name
                    : $this->account_name,
                'account_number' => $isModel
                    ? $this->trx_spread_payment_account_number
                    : $this->account_number,
            ],
            'note' => $isModel ? $this->trx_spread_payment_note : $this->note,
            'created_at' => $isModel
                ? $this->trx_spread_payment_created_datetime?->toAtomString()
                : $this->created_at,
            'approved_at' => $isModel
                ? $this->trx_spread_payment_approved_datetime?->toAtomString()
                : $this->approved_at,
            'paid_at' => $isModel
                ? $this->trx_spread_payment_paid_datetime?->toAtomString()
                : $this->paid_at,
        ];
    }
}
