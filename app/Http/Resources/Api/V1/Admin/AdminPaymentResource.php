<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Models\Trx;
use App\Models\TrxPaymentTransfer;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminPaymentResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $payment = $this->resource instanceof TrxPaymentTransfer ? $this->resource : null;
        $trx = $payment?->trx;
        $buyerType = $trx?->trx_buyer_type ?? $this->resource->buyer_type;

        if ($trx && $payment) {
            $trx->setRelation('paymentTransfer', $payment);
        }

        $orderData = $trx ? (new AdminOrderResource($trx))->resolve($request) : null;

        return [
            'id' => (int) ($payment?->payment_transfer_id ?? $this->resource->id),
            'transaction' => [
                'id' => (int) ($payment?->payment_transfer_trx_id ?? $this->resource->trx_id),
                'code' => $trx?->trx_code ?? $this->resource->transaction_code
                    ?? $this->resource->trx_code,
                'type' => $trx?->trx_type ?? $this->resource->transaction_type,
                'order_type' => $orderData['order_type'] ?? [
                    'code' => ($this->resource->is_preorder ?? false) ? 'preorder' : 'regular',
                    'label' => ($this->resource->is_preorder ?? false) ? 'PO' : 'Reguler',
                ],
                'parent_transaction_id' => (int) ($trx?->trx_parent_trx_id
                    ?? $this->resource->parent_transaction_id
                    ?? 0),
                'is_preorder' => (bool) ($trx?->trx_is_preorder
                    ?? $this->resource->is_preorder
                    ?? false),
                'status' => $trx?->trx_status ?? $this->resource->transaction_status,
                'payment_method' => $trx?->trx_payment_method
                    ?? $this->resource->transaction_payment_method,
                'shipping_method' => $trx?->trx_shipping_method
                    ?? $this->resource->transaction_shipping_method,
                'ordered_at' => $trx?->trx_datetime?->toAtomString()
                    ?? $this->toAtomString($this->resource->transaction_ordered_at),
            ],
            'seller' => $orderData['seller'] ?? null,
            'buyer' => $orderData['buyer'] ?? [
                'type' => $buyerType,
                'id' => (int) ($trx?->trx_buyer_id ?? $this->resource->buyer_id),
                'code' => $buyerType === 'customer'
                    ? null
                    : ($trx?->buyer?->member_code ?? $this->resource->buyer_member_code),
                'name' => $buyerType === 'customer'
                    ? ($trx?->buyerCustomer?->customer_name ?? $this->resource->buyer_customer_name)
                    : ($trx?->buyer?->member_name ?? $this->resource->buyer_member_name),
            ],
            'is_preorder' => (bool) ($orderData['is_preorder'] ?? false),
            'preorder' => $orderData['preorder'] ?? null,
            'shipping' => $orderData['shipping'] ?? null,
            'payment' => $this->payment($payment, $trx),
            'totals' => $this->totals($trx),
            'bill_amount' => (int) ($payment?->payment_transfer_bill_amount ?? $this->resource->bill_amount ?? 0),
            'amount' => (int) ($payment?->payment_transfer_amount ?? $this->resource->amount ?? 0),
            'status' => $payment?->payment_transfer_approval_status ?? $this->resource->status,
            'account_name' => $payment?->payment_transfer_account_name ?? $this->resource->account_name,
            'account_number' => $payment?->payment_transfer_account_number ?? $this->resource->account_number,
            'transferred_at' => $this->toAtomString(
                $payment?->payment_transfer_datetime ?? $this->resource->transferred_at,
            ),
            'items' => $trx?->details->map(fn ($detail): array => [
                'product_id' => (int) $detail->trx_detail_product_id,
                'code' => $detail->trx_detail_product_code,
                'name' => $detail->trx_detail_product_name,
                'quantity' => (int) $detail->trx_detail_qty,
                'net_price' => (int) $detail->trx_detail_nett_price,
            ])->values() ?? [],
        ];
    }

    /** @return array<string, int>|null */
    private function totals(?Trx $trx): ?array
    {
        if ($trx) {
            $insurance = match ($trx->trx_shipping_method) {
                'courier_express' => (int) ($trx->shippingExpress?->shipping_courier_express_insurance ?? 0),
                'courier_instant' => (int) ($trx->shippingInstant?->shipping_courier_instant_insurance ?? 0),
                'courier_manual' => (int) ($trx->shippingManual?->shipping_courier_manual_insurance ?? 0),
                default => 0,
            };
            $shipping = match ($trx->trx_shipping_method) {
                'courier_express' => (int) ($trx->shippingExpress?->shipping_courier_express_cost ?? 0),
                'courier_instant' => (int) ($trx->shippingInstant?->shipping_courier_instant_cost ?? 0),
                'courier_manual' => (int) ($trx->shippingManual?->shipping_courier_manual_price ?? 0),
                default => 0,
            };

            return [
                'product_total' => (int) $trx->trx_total_price,
                'discount_value' => (int) $trx->trx_discount_value,
                'after_discount' => (int) $trx->trx_grand_total_price,
                'shipping_cost' => $shipping,
                'shipping_cost_insurance' => $insurance,
                'shipping_cost_total' => (int) $trx->trx_shipping_cost,
                'payment_charge' => (int) $trx->trx_payment_charge,
                'grand_total' => (int) $trx->trx_grand_total_nett_price,
                'bill_amount' => (int) $trx->trx_bill_amount,
            ];
        }

        if (! isset($this->resource->shipping_cost_total)) {
            return null;
        }

        return [
            'product_total' => (int) ($this->resource->product_total ?? 0),
            'discount_value' => (int) ($this->resource->discount_value ?? 0),
            'after_discount' => (int) ($this->resource->after_discount ?? 0),
            'shipping_cost' => (int) ($this->resource->shipping_cost ?? 0),
            'shipping_cost_insurance' => (int) ($this->resource->shipping_cost_insurance ?? 0),
            'shipping_cost_total' => (int) $this->resource->shipping_cost_total,
            'payment_charge' => (int) ($this->resource->payment_charge ?? 0),
            'grand_total' => (int) ($this->resource->grand_total ?? 0),
            'bill_amount' => (int) ($this->resource->bill_amount ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function payment(?TrxPaymentTransfer $payment, ?Trx $trx): array
    {
        $bank = $payment?->bank;

        return [
            'id' => (int) ($payment?->payment_transfer_id ?? $this->resource->id),
            'bank' => [
                'id' => (int) ($payment?->payment_transfer_bank_id ?? $this->resource->bank_id),
                'code' => $bank?->bank_code ?? $this->resource->bank_code,
                'name' => $bank?->bank_name ?? $this->resource->bank_name,
                'account_name' => $payment?->payment_transfer_account_name
                    ?? $this->resource->account_name,
                'account_number' => $payment?->payment_transfer_account_number
                    ?? $this->resource->account_number,
            ],
            'bill_amount' => (int) ($payment?->payment_transfer_bill_amount ?? $this->resource->bill_amount),
            'amount' => (int) ($payment?->payment_transfer_amount ?? $this->resource->amount),
            'receipt_url' => MediaUrl::temporaryPrivateUrl(
                $payment?->payment_transfer_receipt_file ?? $this->resource->receipt_url,
                'admin',
            ),
            'status' => $payment?->payment_transfer_approval_status ?? $this->resource->status,
            'administrator_id' => (int) ($payment?->payment_transfer_approval_admin_id ?? $this->resource->administrator_id),
            'note' => $payment?->payment_transfer_note ?? $this->resource->note,
            'transferred_at' => $this->toAtomString(
                $payment?->payment_transfer_datetime ?? $this->resource->transferred_at,
            ),
            'approved_at' => $this->toAtomString(
                $payment?->payment_transfer_approval_datetime ?? $this->resource->approved_at,
            ),
            'spread_payment' => $this->spreadPayment($trx),
        ];
    }

    /** @return array<string, mixed>|null */
    private function spreadPayment(?Trx $trx): ?array
    {
        $spread = $trx?->relationLoaded('spreadPayments')
            ? $trx->spreadPayments?->first()
            : null;
        $spreadId = $spread?->getKey() ?? $this->resource->spread_payment_id;

        if (! $spreadId) {
            return null;
        }

        return [
            'id' => (int) $spreadId,
            'amount' => (int) ($spread?->trx_spread_payment_amount ?? $this->resource->spread_payment_amount),
            'percentage' => (float) ($spread?->trx_spread_payment_percentage ?? $this->resource->spread_payment_percentage),
            'bank' => [
                'id' => (int) ($spread?->trx_spread_payment_bank_id ?? $this->resource->spread_payment_bank_id),
                'code' => $spread?->bank?->bank_code ?? $this->resource->spread_payment_bank_code,
                'name' => $spread?->bank?->bank_name ?? $this->resource->spread_payment_bank_name,
                'account_name' => $spread?->trx_spread_payment_account_name
                    ?? $this->resource->spread_payment_account_name,
                'account_number' => $spread?->trx_spread_payment_account_number
                    ?? $this->resource->spread_payment_account_number,
            ],
            'receipt_url' => MediaUrl::temporaryPrivateUrl(
                $spread?->trx_spread_payment_receipt_file ?? $this->resource->spread_payment_receipt_url,
                'admin',
            ),
            'transferred_at' => $this->toAtomString(
                $spread?->trx_spread_payment_transfer_datetime
                    ?? $this->resource->spread_payment_transferred_at,
            ),
            'status' => $spread?->trx_spread_payment_status ?? $this->resource->spread_payment_status,
        ];
    }

    private function toAtomString(mixed $value): ?string
    {
        return blank($value) ? null : Carbon::parse($value)->toAtomString();
    }
}
