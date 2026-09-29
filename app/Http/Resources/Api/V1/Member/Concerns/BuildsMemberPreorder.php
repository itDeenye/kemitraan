<?php

namespace App\Http\Resources\Api\V1\Member\Concerns;

use App\Models\Member;
use App\Models\Trx;
use App\Models\Warehouse;
use Illuminate\Support\Collection;

trait BuildsMemberPreorder
{
    /** @return array<string, mixed>|null */
    private function memberPreorder(Trx $trx): ?array
    {
        if (! $trx->relationLoaded('preorderChain') || $trx->preorderChain->isEmpty()) {
            return null;
        }

        /** @var Trx $originTransaction */
        $originTransaction = $trx->preorderChain->first();
        /** @var Trx $currentMemberTransaction */
        $currentMemberTransaction = $trx->relationLoaded('preorderCurrentTransaction')
            ? $trx->preorderCurrentTransaction
            : $trx;

        return [
            'current_transaction_id' => (int) $trx->getKey(),
            'current_member_transaction_id' => (int) $currentMemberTransaction->getKey(),
            'origin' => [
                'transaction' => $this->memberChainTransaction($originTransaction),
                'buyer' => $this->memberChainParty($originTransaction, 'buyer'),
            ],
            'chain' => $this->memberPreorderChain($trx),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function memberPreorderChain(Trx $trx): array
    {
        /** @var Collection<int, array<string, mixed>>|null $expectedChain */
        $expectedChain = $trx->relationLoaded('preorderExpectedChain')
            ? $trx->preorderExpectedChain
            : null;

        if ($expectedChain && $expectedChain->isNotEmpty()) {
            return $expectedChain->values()->map(
                fn (array $step): array => [
                    'sequence' => (int) $step['sequence'],
                    'transaction' => $this->memberExpectedChainTransaction(
                        $step['transaction'] instanceof Trx ? $step['transaction'] : null,
                    ),
                    'seller' => $this->memberExpectedChainParty($step['seller']),
                    'buyer' => $this->memberExpectedChainParty($step['buyer']),
                    'is_projected' => (bool) $step['is_projected'],
                ],
            )->all();
        }

        return $trx->preorderChain->values()->map(
            fn (Trx $transaction, int $index): array => [
                'sequence' => $index + 1,
                'transaction' => [
                    ...$this->memberChainTransaction($transaction),
                    'is_projected' => false,
                ],
                'seller' => $this->memberChainParty($transaction, 'seller'),
                'buyer' => $this->memberChainParty($transaction, 'buyer'),
                'is_projected' => false,
            ],
        )->all();
    }

    /** @return array<string, mixed> */
    private function memberExpectedChainTransaction(?Trx $transaction): array
    {
        if ($transaction) {
            return [
                ...$this->memberChainTransaction($transaction),
                'is_projected' => false,
            ];
        }

        return [
            'id' => null,
            'code' => null,
            'status' => 'waiting_previous_step',
            'status_label' => 'Menunggu tahap sebelumnya',
            'payment_status' => 'pending',
            'payment_status_label' => 'Belum Dibayar',
            'ordered_at' => null,
            'is_projected' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function memberChainTransaction(Trx $trx): array
    {
        $status = (string) $trx->trx_status;
        $paymentStatus = (string) ($trx->paymentTransfer?->payment_transfer_approval_status ?? 'pending');

        return [
            'id' => (int) $trx->getKey(),
            'code' => $trx->trx_code,
            'status' => $status,
            'status_label' => $this->memberChainStatusLabel($status),
            'payment_status' => $paymentStatus,
            'payment_status_label' => $this->memberChainPaymentStatusLabel($paymentStatus),
            'ordered_at' => $trx->trx_datetime?->toAtomString(),
        ];
    }

    private function memberChainStatusLabel(string $status): string
    {
        return match ($status) {
            'waiting_stock_screening' => 'Menunggu Screening Stok',
            'waiting_payment' => 'Menunggu Pembayaran',
            'waiting_payment_approval' => 'Menunggu Verifikasi Pembayaran',
            'processing' => 'Diproses',
            'shipped' => 'Dikirim',
            'reship_required' => 'Perlu Dikirim Ulang',
            'ready_to_pickup' => 'Siap Diambil',
            'received' => 'Siap Diterima',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            'rejected' => 'Ditolak',
            default => $status,
        };
    }

    private function memberChainPaymentStatusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Menunggu Verifikasi',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => 'Belum Dibayar',
        };
    }

    /**
     * @param  array{type: string, id: int, model: Member|Warehouse|null}  $step
     * @return array<string, mixed>
     */
    private function memberExpectedChainParty(array $step): array
    {
        $type = (string) $step['type'];
        $party = $step['model'];

        return [
            'type' => $type,
            'id' => (int) $step['id'],
            'code' => $party instanceof Member ? $party->member_code : null,
            'name' => match (true) {
                $party instanceof Warehouse => $party->warehouse_name,
                $party instanceof Member => $party->member_name,
                default => null,
            },
        ];
    }

    /** @return array<string, mixed> */
    private function memberChainParty(Trx $trx, string $side): array
    {
        $type = (string) $trx->{"trx_{$side}_type"};
        $id = (int) $trx->{"trx_{$side}_id"};
        $party = match (true) {
            $side === 'seller' && $type === 'warehouse' => $trx->sellerWarehouse,
            $side === 'buyer' && $type === 'customer' => $trx->buyerCustomer,
            $side === 'seller' => $trx->seller,
            default => $trx->buyer,
        };

        return [
            'type' => $type,
            'id' => $id,
            'code' => in_array($type, ['warehouse', 'customer'], true)
                ? null
                : $party?->member_code,
            'name' => match ($type) {
                'warehouse' => $party?->warehouse_name,
                'customer' => $party?->customer_name,
                default => $party?->member_name,
            },
        ];
    }
}
