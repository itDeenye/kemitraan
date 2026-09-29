<?php

namespace App\Services\Shipping;

use App\Models\ReturnModel;
use App\Models\ReturnStatusLog;
use App\Models\ShippingCourierExpress;
use App\Models\ShippingCourierExpressStatus;
use App\Models\ShippingCourierInstant;
use App\Models\ShippingCourierInstantStatus;
use App\Models\Trx;
use App\Services\Purchase\PreorderChainService;
use App\Services\Return\ReturnFulfillmentService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Throwable;

class StcShippingCallbackService
{
    public function __construct(
        private readonly ReturnFulfillmentService $returnFulfillmentService,
        private readonly PreorderChainService $preorderChainService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handle(array $payload): array
    {
        $event = (string) $payload['method'];
        $results = [];
        $failed = [];
        $idempotentCount = 0;
        $ignoredCount = 0;

        foreach ($payload['data'] as $item) {
            try {
                $result = DB::transaction(
                    fn (): array => $this->processItem($event, $item),
                    3
                );
                $results[] = $result;
                $idempotentCount += $result['idempotent'] ? 1 : 0;
                $ignoredCount += $result['ignored'] ? 1 : 0;
            } catch (ModelNotFoundException $exception) {
                $failed[] = [
                    'order_id' => (string) $item['order_id'],
                    'message' => 'Nomor pesanan pada pemberitahuan pengiriman tidak ditemukan.',
                ];
            } catch (Throwable $exception) {
                report($exception);
                $failed[] = [
                    'order_id' => (string) $item['order_id'],
                    'message' => 'Pemberitahuan status pengiriman belum berhasil diproses.',
                ];
            }
        }

        return [
            'event' => $event,
            'total' => count($payload['data']),
            'processed' => count($results),
            'idempotent' => $idempotentCount,
            'ignored' => $ignoredCount,
            'failed' => count($failed),
            'results' => $results,
            'errors' => $failed,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function processItem(string $event, array $item): array
    {
        $orderId = (string) $item['order_id'];
        $shipping = $this->expressShipping($orderId);

        if ($shipping) {
            return $this->processExpress($shipping, $event, $item);
        }

        $shipping = $this->instantShipping($orderId);

        return $this->processInstant($shipping, $event, $item);
    }

    private function expressShipping(string $orderId): ?ShippingCourierExpress
    {
        $candidates = ShippingCourierExpress::query()
            ->where('shipping_courier_express_order_id', $orderId)
            ->lockForUpdate()
            ->get();
        if ($candidates->count() <= 1) {
            return $candidates->first();
        }

        $transactionIds = $candidates
            ->where('shipping_courier_express_ref_type', 'trx')
            ->pluck('shipping_courier_express_ref_id')
            ->map(fn (mixed $id): int => (int) $id);
        $nonTerminalIds = Trx::query()
            ->whereIn('trx_parent_trx_id', $transactionIds)
            ->pluck('trx_parent_trx_id')
            ->map(fn (mixed $id): int => (int) $id);

        return $candidates
            ->where('shipping_courier_express_ref_type', 'trx')
            ->reject(fn (ShippingCourierExpress $candidate): bool => $nonTerminalIds->contains(
                (int) $candidate->shipping_courier_express_ref_id,
            ))
            ->sortByDesc('shipping_courier_express_ref_id')
            ->first()
            ?? $candidates->sortByDesc('shipping_courier_express_id')->first();
    }

    private function instantShipping(string $orderId): ShippingCourierInstant
    {
        $candidates = ShippingCourierInstant::query()
            ->where('shipping_courier_instant_order_id', $orderId)
            ->lockForUpdate()
            ->get();
        if ($candidates->count() <= 1) {
            return $candidates->firstOrFail();
        }

        $transactionIds = $candidates
            ->where('shipping_courier_instant_ref_type', 'trx')
            ->pluck('shipping_courier_instant_ref_id')
            ->map(fn (mixed $id): int => (int) $id);
        $nonTerminalIds = Trx::query()
            ->whereIn('trx_parent_trx_id', $transactionIds)
            ->pluck('trx_parent_trx_id')
            ->map(fn (mixed $id): int => (int) $id);

        return $candidates
            ->where('shipping_courier_instant_ref_type', 'trx')
            ->reject(fn (ShippingCourierInstant $candidate): bool => $nonTerminalIds->contains(
                (int) $candidate->shipping_courier_instant_ref_id,
            ))
            ->sortByDesc('shipping_courier_instant_ref_id')
            ->first()
            ?? $candidates->sortByDesc('shipping_courier_instant_id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function processExpress(ShippingCourierExpress $shipping, string $event, array $item): array
    {
        if (filled($item['awb'] ?? null)
            && $shipping->shipping_courier_express_awb !== $item['awb']) {
            $shipping->update(['shipping_courier_express_awb' => $item['awb']]);
        }

        $existing = ShippingCourierExpressStatus::query()
            ->where('shipping_courier_express_status_shipping_courier_express_id', $shipping->getKey())
            ->where('shipping_courier_express_status_value', $event)
            ->where('shipping_courier_express_status_external_ref_code', $item['order_id'])
            ->first();
        $currentStatus = ShippingCourierExpressStatus::query()
            ->where('shipping_courier_express_status_shipping_courier_express_id', $shipping->getKey())
            ->latest('shipping_courier_express_status_id')
            ->value('shipping_courier_express_status_value');
        $ignored = ! $existing && $this->shouldIgnoreEvent($currentStatus, $event);

        if (! $existing && ! $ignored) {
            ShippingCourierExpressStatus::query()->create([
                'shipping_courier_express_status_shipping_courier_express_id' => $shipping->getKey(),
                'shipping_courier_express_status_ref_type' => $shipping->shipping_courier_express_ref_type,
                'shipping_courier_express_status_ref_id' => $shipping->shipping_courier_express_ref_id,
                'shipping_courier_express_status_value' => $event,
                'shipping_courier_express_status_note' => $this->note($event, $item),
                'shipping_courier_express_status_datetime' => $this->eventTime($event, $item),
                'shipping_courier_express_status_ref_code' => $this->referenceCode(
                    $shipping->shipping_courier_express_ref_type,
                    (int) $shipping->shipping_courier_express_ref_id,
                ),
                'shipping_courier_express_status_external_ref_code' => $item['order_id'],
            ]);
        }

        if (! $ignored) {
            $this->synchronizeTransaction(
                (string) $shipping->shipping_courier_express_ref_type,
                (int) $shipping->shipping_courier_express_ref_id,
                $event,
            );
            $this->synchronizeReturn(
                'courier_express',
                (int) $shipping->getKey(),
                (string) $shipping->shipping_courier_express_ref_type,
                (int) $shipping->shipping_courier_express_ref_id,
                $event,
            );
        }

        return $this->result('courier_express', $shipping, $event, (bool) $existing, $ignored);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function processInstant(ShippingCourierInstant $shipping, string $event, array $item): array
    {
        if (filled($item['awb'] ?? null)
            && $shipping->shipping_courier_instant_awb !== $item['awb']) {
            $shipping->update(['shipping_courier_instant_awb' => $item['awb']]);
        }

        $existing = ShippingCourierInstantStatus::query()
            ->where('shipping_courier_instant_status_shipping_courier_instant_id', $shipping->getKey())
            ->where('shipping_courier_instant_status_value', $event)
            ->where('shipping_courier_instant_status_external_ref_code', $item['order_id'])
            ->first();
        $currentStatus = ShippingCourierInstantStatus::query()
            ->where('shipping_courier_instant_status_shipping_courier_instant_id', $shipping->getKey())
            ->latest('shipping_courier_instant_status_id')
            ->value('shipping_courier_instant_status_value');
        $ignored = ! $existing && $this->shouldIgnoreEvent($currentStatus, $event);

        if (! $existing && ! $ignored) {
            ShippingCourierInstantStatus::query()->create([
                'shipping_courier_instant_status_shipping_courier_instant_id' => $shipping->getKey(),
                'shipping_courier_instant_status_ref_type' => $shipping->shipping_courier_instant_ref_type,
                'shipping_courier_instant_status_ref_id' => $shipping->shipping_courier_instant_ref_id,
                'shipping_courier_instant_status_value' => $event,
                'shipping_courier_instant_status_note' => $this->note($event, $item),
                'shipping_courier_instant_status_datetime' => $this->eventTime($event, $item),
                'shipping_courier_instant_status_ref_code' => $this->referenceCode(
                    $shipping->shipping_courier_instant_ref_type,
                    (int) $shipping->shipping_courier_instant_ref_id,
                ),
                'shipping_courier_instant_status_external_ref_code' => $item['order_id'],
            ]);
        }

        if (! $ignored) {
            $this->synchronizeTransaction(
                (string) $shipping->shipping_courier_instant_ref_type,
                (int) $shipping->shipping_courier_instant_ref_id,
                $event,
            );
            $this->synchronizeReturn(
                'courier_instant',
                (int) $shipping->getKey(),
                (string) $shipping->shipping_courier_instant_ref_type,
                (int) $shipping->shipping_courier_instant_ref_id,
                $event,
            );
        }

        return $this->result('courier_instant', $shipping, $event, (bool) $existing, $ignored);
    }

    private function synchronizeTransaction(string $refType, int $refId, string $event): void
    {
        if ($refType !== 'trx') {
            return;
        }

        $transaction = Trx::query()->whereKey($refId)->lockForUpdate()->first();
        if (! $transaction) {
            return;
        }

        if (in_array($event, ['canceled_packages', 'returned_packages', 'return_finished_package'], true)) {
            $this->markTransactionForReshipment($transaction);

            return;
        }

        $status = match (true) {
            in_array($event, ['processed_packages', 'shipped_packages'], true) => 'shipped',
            $event === 'finished_packages' => $transaction->trx_buyer_type === 'customer'
                ? 'completed'
                : 'received',
            default => null,
        };
        if ($status === null) {
            return;
        }

        if (in_array($transaction->trx_status, ['processing', 'shipped'], true)) {
            $transaction->update([
                'trx_status' => $status,
                'trx_status_datetime' => now(),
            ]);
            $this->preorderChainService->synchronizeFinalShipment($transaction->refresh());
        }
    }

    private function markTransactionForReshipment(Trx $transaction): void
    {
        if (! in_array($transaction->trx_status, ['processing', 'shipped', 'received', 'completed'], true)) {
            return;
        }

        $transaction->update([
            'trx_status' => 'reship_required',
            'trx_status_datetime' => now(),
        ]);
        $this->preorderChainService->synchronizeFinalShipment($transaction->refresh());
    }

    private function synchronizeReturn(
        string $shippingMethod,
        int $shippingId,
        string $referenceType,
        int $referenceId,
        string $event,
    ): void {
        if (! in_array($referenceType, ['return_company', 'return_replacement'], true)
            || ! $this->isLatestReturnShipping($shippingMethod, $shippingId, $referenceType, $referenceId)) {
            return;
        }

        $return = ReturnModel::query()
            ->whereKey($referenceId)
            ->lockForUpdate()
            ->first();
        if (! $return) {
            return;
        }

        if (in_array($event, ['canceled_packages', 'returned_packages', 'return_finished_package'], true)) {
            $this->returnFulfillmentService->failShipping($return, $referenceType, $event);

            return;
        }

        if ($referenceType !== 'return_company'
            || ! in_array($event, ['shipped_packages', 'finished_packages'], true)
            || ! in_array($return->return_status, ['waiting_member_shipment', 'return_in_transit'], true)
            || $return->return_status === 'return_in_transit') {
            return;
        }

        $return->update(['return_status' => 'return_in_transit']);
        ReturnStatusLog::query()->create([
            'return_status_log_return_id' => $return->getKey(),
            'return_status_log_status' => 'return_in_transit',
            'return_status_log_note' => 'Barang retur telah diserahkan ke kurir.',
            'return_status_log_created_by' => 0,
            'return_status_log_created_datetime' => now(),
        ]);
    }

    private function isLatestReturnShipping(
        string $shippingMethod,
        int $shippingId,
        string $referenceType,
        int $referenceId,
    ): bool {
        [$model, $referenceTypeColumn, $referenceIdColumn, $primaryKey] = match ($shippingMethod) {
            'courier_express' => [
                ShippingCourierExpress::query(),
                'shipping_courier_express_ref_type',
                'shipping_courier_express_ref_id',
                'shipping_courier_express_id',
            ],
            'courier_instant' => [
                ShippingCourierInstant::query(),
                'shipping_courier_instant_ref_type',
                'shipping_courier_instant_ref_id',
                'shipping_courier_instant_id',
            ],
            default => [null, '', '', ''],
        };
        if (! $model) {
            return false;
        }

        return (int) $model
            ->where($referenceTypeColumn, $referenceType)
            ->where($referenceIdColumn, $referenceId)
            ->max($primaryKey) === $shippingId;
    }

    private function referenceCode(string $refType, int $refId): string
    {
        if ($refType !== 'trx') {
            return mb_substr((string) DB::table('return')->where('return_id', $refId)->value('return_code'), 0, 50);
        }

        return mb_substr((string) Trx::query()->whereKey($refId)->value('trx_code'), 0, 50);
    }

    /** @param array<string, mixed> $item */
    private function eventTime(string $event, array $item): Carbon
    {
        $field = match ($event) {
            'shipped_packages' => 'shipped_at',
            'finished_packages' => 'finished_at',
            'returned_packages' => 'returned_at',
            'return_finished_package' => 'return_finished_at',
            'canceled_packages' => 'rejected_at',
            'problem_packages' => 'problem_at',
            default => 'date',
        };
        $value = $item[$field] ?? $item['date'] ?? null;

        return filled($value) ? Carbon::parse($value) : now();
    }

    /** @param array<string, mixed> $item */
    private function note(string $event, array $item): string
    {
        if (filled($item['reason'] ?? null)) {
            return mb_substr('Kurir: '.(string) $item['reason'], 0, 255);
        }

        return match ($event) {
            'processed_packages' => 'Paket sedang diproses oleh kurir.',
            'shipped_packages' => 'Paket sedang dikirim oleh kurir.',
            'canceled_packages' => 'Pengiriman dibatalkan oleh layanan pengiriman.',
            'finished_packages' => 'Paket telah tiba menurut layanan pengiriman dan menunggu konfirmasi penerimaan.',
            'returned_packages' => 'Paket dikembalikan oleh layanan pengiriman.',
            'problem_packages' => 'Pengiriman mengalami kendala dan menunggu tindak lanjut layanan pengiriman.',
            'return_finished_package' => 'Pengembalian paket kepada pengirim telah selesai.',
            default => 'Status pengiriman diperbarui oleh kurir.',
        };
    }

    private function shouldIgnoreEvent(?string $current, string $incoming): bool
    {
        if ($current === null || $current === 'pending') {
            return false;
        }
        if ($current === 'return_finished_package') {
            return true;
        }

        $rank = [
            'pending' => 0,
            'processed_packages' => 10,
            'shipped_packages' => 20,
            'problem_packages' => 25,
            'canceled_packages' => 30,
            'returned_packages' => 35,
            'return_finished_package' => 40,
            'finished_packages' => 50,
        ];

        if (in_array($incoming, ['canceled_packages', 'returned_packages', 'return_finished_package'], true)) {
            return in_array($current, ['canceled_packages', 'returned_packages'], true)
                && ($rank[$incoming] ?? 0) < ($rank[$current] ?? 0);
        }
        if (in_array($current, ['canceled_packages', 'returned_packages', 'finished_packages'], true)) {
            return true;
        }

        return ($rank[$incoming] ?? 0) < ($rank[$current] ?? 0);
    }

    /** @return array<string, mixed> */
    private function result(
        string $method,
        Model $shipping,
        string $event,
        bool $idempotent,
        bool $ignored,
    ): array {
        $express = $method === 'courier_express';

        return [
            'order_id' => $express
                ? $shipping->shipping_courier_express_order_id
                : $shipping->shipping_courier_instant_order_id,
            'shipping_method' => $method,
            'reference_type' => $express
                ? $shipping->shipping_courier_express_ref_type
                : $shipping->shipping_courier_instant_ref_type,
            'reference_id' => (int) ($express
                ? $shipping->shipping_courier_express_ref_id
                : $shipping->shipping_courier_instant_ref_id),
            'status' => $event,
            'idempotent' => $idempotent,
            'ignored' => $ignored,
        ];
    }
}
