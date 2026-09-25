<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

class MemberPurchasePaymentResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->payment_transfer_id,
            'bank' => [
                'id' => (int) $this->payment_transfer_bank_id,
                'code' => $this->bank?->bank_code,
                'name' => $this->bank?->bank_name,
                'account_name' => $this->payment_transfer_account_name,
                'account_number' => $this->payment_transfer_account_number,
            ],
            'bill_amount' => (int) $this->payment_transfer_bill_amount,
            'amount' => (int) $this->payment_transfer_amount,
            'receipt_url' => MediaUrl::temporaryPrivateUrl(
                $this->payment_transfer_receipt_file,
                'member',
            ),
            'status' => [
                'code' => $this->payment_transfer_approval_status,
                'label' => match ($this->payment_transfer_approval_status) {
                    'submitted' => 'Menunggu Verifikasi',
                    'approved' => 'Disetujui',
                    'rejected' => 'Ditolak',
                    default => 'Belum Dibayar',
                },
            ],
            'note' => $this->payment_transfer_note,
            'transferred_at' => $this->payment_transfer_receipt_file
                ? $this->payment_transfer_datetime?->toAtomString()
                : null,
            'verified_at' => $this->payment_transfer_approval_datetime?->toAtomString(),
            'spread_payment' => $this->whenLoaded('trx', function (): ?array {
                $spread = $this->trx?->spreadPayments?->first();
                if (! $spread) {
                    return null;
                }

                return [
                    'id' => (int) $spread->getKey(),
                    'amount' => (int) $spread->trx_spread_payment_amount,
                    'percentage' => (float) $spread->trx_spread_payment_percentage,
                    'bank' => [
                        'id' => (int) $spread->trx_spread_payment_bank_id,
                        'code' => $spread->bank?->bank_code,
                        'name' => $spread->bank?->bank_name,
                        'account_name' => $spread->trx_spread_payment_account_name,
                        'account_number' => $spread->trx_spread_payment_account_number,
                    ],
                    'receipt_url' => MediaUrl::temporaryPrivateUrl(
                        $spread->trx_spread_payment_receipt_file,
                        'member',
                    ),
                    'status' => $spread->trx_spread_payment_status,
                    'transferred_at' => $spread->trx_spread_payment_transfer_datetime?->toAtomString(),
                ];
            }),
        ];
    }
}
