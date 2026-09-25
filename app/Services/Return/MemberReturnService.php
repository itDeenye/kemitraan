<?php

namespace App\Services\Return;

use App\Exceptions\ProcessException;
use App\Libraries\DataTable;
use App\Models\GoodsReceive;
use App\Models\GoodsReceiveDetail;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberAddress;
use App\Models\ReturnDetail;
use App\Models\ReturnModel;
use App\Models\ReturnStatusLog;
use App\Models\Trx;
use App\Models\Warehouse;
use App\Services\Document\DocumentCodeService;
use App\Support\BusinessConfig;
use App\Support\MediaUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MemberReturnService
{
    public function __construct(
        private readonly DocumentCodeService $documentCodeService,
        private readonly ReturnFulfillmentService $fulfillmentService,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination: array<string, mixed>}
     */
    public function returns(MemberAccount $account, array $params): array
    {
        $member = $this->member($account);
        $returnTable = (new ReturnModel)->getTable();
        $detailTable = (new ReturnDetail)->getTable();
        $trxTable = (new Trx)->getTable();
        $receiveTable = (new GoodsReceive)->getTable();
        $detailSummary = DB::table($detailTable)
            ->select("{$detailTable}.return_detail_return_id")
            ->selectRaw('COUNT(*) AS product_count')
            ->selectRaw("SUM({$detailTable}.return_detail_qty) AS total_quantity")
            ->selectRaw("SUM({$detailTable}.return_detail_received_qty) AS received_quantity")
            ->selectRaw(
                "SUM({$detailTable}.return_detail_qty - {$detailTable}.return_detail_received_qty) AS not_received_quantity"
            )
            ->groupBy("{$detailTable}.return_detail_return_id");

        $dataTable = DataTable::select([
            "{$returnTable}.return_id as id",
            "{$returnTable}.return_code as code",
            "{$receiveTable}.goods_receive_trx_id as transaction_id",
            "{$trxTable}.trx_code as transaction_code",
            "{$returnTable}.return_goods_receive_id as goods_receive_id",
            "{$receiveTable}.goods_receive_number as goods_receive_number",
            "{$returnTable}.return_description as description",
            "{$returnTable}.return_status as status",
            "{$returnTable}.return_shipping_method as shipping_method",
            "{$returnTable}.return_shipping_cost as shipping_cost",
            "{$returnTable}.return_shipping_cost_bearer as shipping_cost_bearer",
            "{$returnTable}.return_replacement_shipping_method as replacement_shipping_method",
            "{$returnTable}.return_replacement_shipping_cost as replacement_shipping_cost",
            "{$returnTable}.return_created_datetime as created_at",
            'detail_summary.product_count as product_count',
            'detail_summary.total_quantity as total_quantity',
            'detail_summary.received_quantity as received_quantity',
            'detail_summary.not_received_quantity as not_received_quantity',
        ])
            ->from($returnTable)
            ->leftJoin(
                $receiveTable,
                "{$receiveTable}.goods_receive_id = {$returnTable}.return_goods_receive_id"
            )
            ->leftJoin($trxTable, "{$trxTable}.trx_id = {$receiveTable}.goods_receive_trx_id")
            ->leftJoinSub(
                $detailSummary,
                'detail_summary',
                "detail_summary.return_detail_return_id = {$returnTable}.return_id"
            )
            ->where("{$returnTable}.return_member_id", $member->getKey())
            ->search(['code', 'transaction_code', 'goods_receive_number', 'description'])
            ->defaultSort('-id');

        if (filter_var(data_get($params, 'filter.action_required'), FILTER_VALIDATE_BOOLEAN)) {
            $dataTable->where(function ($query) use ($returnTable): void {
                $query->where(function ($actionQuery) use ($returnTable): void {
                    $actionQuery
                        ->where("{$returnTable}.return_status", 'approved')
                        ->where("{$returnTable}.return_shipping_method", 'courier_express');
                })->orWhere(function ($actionQuery) use ($returnTable): void {
                    $actionQuery
                        ->where("{$returnTable}.return_status", 'submitted')
                        ->where("{$returnTable}.return_shipping_method", 'pickup');
                })->orWhere("{$returnTable}.return_status", 'replacement_in_transit');
            });
        }

        return $dataTable->get($params);
    }

    /** @return array{eligible: int, action_required: int, history: int, total_actions: int} */
    public function actionSummary(MemberAccount $account): array
    {
        $member = $this->member($account);
        $eligible = $this->eligibleReceipts($account, ['page' => 1, 'limit' => 1]);
        $eligibleCount = (int) data_get($eligible, 'pagination.total_data', 0);
        $returns = ReturnModel::query()
            ->where('return_member_id', $member->getKey());
        $historyCount = (clone $returns)->count();
        $actionRequiredCount = (clone $returns)
            ->where(function ($query): void {
                $query->where(function ($actionQuery): void {
                    $actionQuery
                        ->where('return_status', 'approved')
                        ->where('return_shipping_method', 'courier_express');
                })->orWhere(function ($actionQuery): void {
                    $actionQuery
                        ->where('return_status', 'submitted')
                        ->where('return_shipping_method', 'pickup');
                })->orWhere('return_status', 'replacement_in_transit');
            })
            ->count();

        return [
            'eligible' => $eligibleCount,
            'action_required' => $actionRequiredCount,
            'history' => $historyCount,
            'total_actions' => $eligibleCount + $actionRequiredCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination: array<string, mixed>}
     */
    public function eligibleReceipts(MemberAccount $account, array $params): array
    {
        $member = $this->member($account);
        $returnMaxDays = $this->returnMaxDays();
        $receiveTable = (new GoodsReceive)->getTable();
        $receiveDetailTable = (new GoodsReceiveDetail)->getTable();
        $returnTable = (new ReturnModel)->getTable();
        $grammar = DB::connection()->getQueryGrammar();
        $quotedReturnTable = $grammar->wrapTable($returnTable);
        $quotedReceiveTable = $grammar->wrapTable($receiveTable);
        $returnDetailTable = (new ReturnDetail)->getTable();
        $trxTable = (new Trx)->getTable();
        $memberTable = (new Member)->getTable();
        $warehouseTable = (new Warehouse)->getTable();
        $defaultAddresses = DB::table('member_address')
            ->where('member_address_is_default', 1);
        $receivedSummary = DB::table($receiveDetailTable)
            ->select("{$receiveDetailTable}.goods_receive_detail_receive_id")
            ->selectRaw("COUNT(DISTINCT {$receiveDetailTable}.goods_receive_detail_product_id) AS product_count")
            ->selectRaw("SUM({$receiveDetailTable}.goods_receive_detail_qty) AS received_quantity")
            ->groupBy("{$receiveDetailTable}.goods_receive_detail_receive_id");
        $returnedSummary = DB::table($returnDetailTable)
            ->join(
                $returnTable,
                "{$returnTable}.return_id",
                '=',
                "{$returnDetailTable}.return_detail_return_id"
            )
            ->select("{$returnTable}.return_goods_receive_id")
            ->selectRaw("SUM({$returnDetailTable}.return_detail_qty) AS returned_quantity")
            ->whereNotIn("{$returnTable}.return_status", ['rejected'])
            ->whereNotNull("{$returnTable}.return_goods_receive_id")
            ->groupBy("{$returnTable}.return_goods_receive_id");

        $dataTable = DataTable::select([
            "{$receiveTable}.goods_receive_id as receive_id",
            "{$receiveTable}.goods_receive_number as receive_number",
            "{$receiveTable}.goods_receive_delivery_note_number as delivery_note_number",
            "{$receiveTable}.goods_receive_trx_id as transaction_id",
            "{$trxTable}.trx_code as transaction_code",
            "{$trxTable}.trx_seller_type as seller_type",
            "{$trxTable}.trx_seller_id as seller_id",
            'seller_member.member_code as seller_code',
            'seller_member.member_name as seller_name',
            'seller_default_address.member_address_full as seller_member_address',
            'seller_default_address.member_address_province_id as seller_member_province_id',
            'seller_default_address.member_address_city_id as seller_member_city_id',
            'seller_default_address.member_address_district_id as seller_member_district_id',
            'seller_default_address.member_address_subdistrict_id as seller_member_subdistrict_id',
            "{$warehouseTable}.warehouse_name as warehouse_name",
            "{$warehouseTable}.warehouse_address as warehouse_address",
            "{$warehouseTable}.warehouse_province_id as warehouse_province_id",
            "{$warehouseTable}.warehouse_city_id as warehouse_city_id",
            "{$warehouseTable}.warehouse_district_id as warehouse_district_id",
            "{$warehouseTable}.warehouse_subdistrict_id as warehouse_subdistrict_id",
            'received_summary.product_count as product_count',
            'received_summary.received_quantity as received_quantity',
        ])
            ->selectRaw(
                "COALESCE({$receiveTable}.goods_receive_status_datetime, {$receiveTable}.goods_receive_created_datetime) AS received_at"
            )
            ->selectRaw('COALESCE(returned_summary.returned_quantity, 0) AS returned_quantity')
            ->selectRaw('? AS return_max_days', [$returnMaxDays])
            ->from($receiveTable)
            ->leftJoin($trxTable, "{$trxTable}.trx_id = {$receiveTable}.goods_receive_trx_id")
            ->leftJoin(
                "{$memberTable} as seller_member",
                "seller_member.member_id = {$trxTable}.trx_seller_id"
            )
            ->leftJoinSub(
                $defaultAddresses,
                'seller_default_address',
                'seller_default_address.member_address_member_id = seller_member.member_id'
            )
            ->leftJoin($warehouseTable, "{$warehouseTable}.warehouse_id = {$trxTable}.trx_seller_id")
            ->leftJoinSub(
                $receivedSummary,
                'received_summary',
                "received_summary.goods_receive_detail_receive_id = {$receiveTable}.goods_receive_id"
            )
            ->leftJoinSub(
                $returnedSummary,
                'returned_summary',
                "returned_summary.return_goods_receive_id = {$receiveTable}.goods_receive_id"
            )
            ->where("{$receiveTable}.goods_receive_buyer_id", $member->getKey())
            ->where("{$receiveTable}.goods_receive_status", 'completed')
            ->where("{$trxTable}.trx_is_preorder", 0)
            ->whereIn("{$trxTable}.trx_status", ['verified', 'completed', 'received'])
            ->whereRaw(
                "COALESCE({$receiveTable}.goods_receive_status_datetime, {$receiveTable}.goods_receive_created_datetime) >= ?",
                [now()->subDays($returnMaxDays)->toDateTimeString()],
            )
            ->whereRaw(
                'received_summary.received_quantity > COALESCE(returned_summary.returned_quantity, 0)'
            )
            ->whereRaw(
                "NOT EXISTS (SELECT 1 FROM {$quotedReturnTable} AS active_return
                    WHERE active_return.return_goods_receive_id = {$quotedReceiveTable}.goods_receive_id
                    AND active_return.return_status != ?)",
                ['rejected'],
            )
            ->search(['receive_number', 'transaction_code', 'seller_code', 'seller_name', 'warehouse_name'])
            ->defaultSort('-received_at')
            ->get($params);

        $receiveIds = collect($dataTable['results'] ?? [])->pluck('receive_id')->map(fn ($id) => (int) $id)->filter()->all();
        $items = collect();
        if (! empty($receiveIds)) {
            $returnedSummaryByDetail = DB::table($returnDetailTable)
                ->join(
                    $returnTable,
                    "{$returnTable}.return_id",
                    '=',
                    "{$returnDetailTable}.return_detail_return_id"
                )
                ->select("{$returnDetailTable}.return_detail_goods_receive_detail_id")
                ->selectRaw("SUM({$returnDetailTable}.return_detail_qty) AS returned_quantity")
                ->whereNotIn("{$returnTable}.return_status", ['rejected'])
                ->whereNotNull("{$returnDetailTable}.return_detail_goods_receive_detail_id")
                ->groupBy("{$returnDetailTable}.return_detail_goods_receive_detail_id");

            $items = DB::table($receiveDetailTable)
                ->join('product', 'product.product_id', '=', "{$receiveDetailTable}.goods_receive_detail_product_id")
                ->leftJoinSub(
                    $returnedSummaryByDetail,
                    'returned_detail_summary',
                    function ($join) use ($receiveDetailTable) {
                        $join->on('returned_detail_summary.return_detail_goods_receive_detail_id', '=', "{$receiveDetailTable}.goods_receive_detail_id");
                    }
                )
                ->whereIn("{$receiveDetailTable}.goods_receive_detail_receive_id", $receiveIds)
                ->select([
                    "{$receiveDetailTable}.goods_receive_detail_id as goods_receive_detail_id",
                    "{$receiveDetailTable}.goods_receive_detail_receive_id as receive_id",
                    "{$receiveDetailTable}.goods_receive_detail_product_id as product_id",
                    'product.product_code as product_code',
                    'product.product_name as product_name',
                    'product.product_image as product_image',
                    'product.product_customer_price as product_price',
                    'product.product_weight as product_weight',
                    "{$receiveDetailTable}.goods_receive_detail_batch_number as batch_number",
                    "{$receiveDetailTable}.goods_receive_detail_expire_date as expire_date",
                    "{$receiveDetailTable}.goods_receive_detail_qty as received_quantity",
                ])
                ->selectRaw('COALESCE(returned_detail_summary.returned_quantity, 0) as returned_quantity')
                ->selectRaw("{$receiveDetailTable}.goods_receive_detail_qty - COALESCE(returned_detail_summary.returned_quantity, 0) as remaining_quantity")
                ->get()
                ->groupBy(fn ($item) => (int) $item->receive_id);
        }

        $dataTable['results'] = collect($dataTable['results'] ?? [])->map(function ($receipt) use ($items) {
            $receiveId = (int) ($receipt->receive_id ?? 0);
            $receiptItems = $items->get($receiveId)
                ?? $items->get((string) $receiveId)
                ?? collect();
            $receipt->items = collect($receiptItems)->values()->all();

            return $receipt;
        });

        return $dataTable;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function returnCourierOptions(MemberAccount $account, array $data): array
    {
        $member = $this->member($account);
        $goodsReceive = $this->eligibleGoodsReceive($member, (int) $data['goods_receive_id']);
        $address = $this->memberAddress($member, (int) $data['address_id']);
        $items = $this->validateReturnItems($goodsReceive, collect($data['items']));
        $return = $this->temporaryReturn($member, $goodsReceive, $items, $address);

        return $this->fulfillmentService->courierOptions(
            $return,
            'return_company',
            $data['couriers'] ?? [],
            $items->map(fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
            ])->values()->all(),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{reference_type: string, results: list<array<string, mixed>>}
     */
    public function returnPickupSchedules(MemberAccount $account, array $data): array
    {
        $member = $this->member($account);
        $goodsReceive = $this->eligibleGoodsReceive($member, (int) $data['goods_receive_id']);
        $address = $this->memberAddress($member, (int) $data['address_id']);
        $items = $this->validateReturnItems($goodsReceive, collect($data['items']));
        $return = $this->temporaryReturn($member, $goodsReceive, $items, $address);

        return $this->fulfillmentService->pickupSchedules($return, 'return_company');
    }

    public function returnDetail(MemberAccount $account, ReturnModel $return): ReturnModel
    {
        $member = $this->member($account);

        return ReturnModel::query()
            ->with([
                'trx',
                'goodsReceive',
                'details.product',
                'details.goodsReceiveDetail',
                'statusLogs',
                'shippingExpress',
                'shippingExpress.latestStatus',
                'shippingInstant',
                'shippingInstant.latestStatus',
                'shippingManual',
                'shippingManual.latestStatus',
                'shippingPickup',
                'shippingPickup.latestStatus',
                'replacementShippingExpress',
                'replacementShippingExpress.latestStatus',
                'replacementShippingInstant',
                'replacementShippingInstant.latestStatus',
                'replacementShippingManual',
                'replacementShippingManual.latestStatus',
                'replacementShippingPickup',
                'replacementShippingPickup.latestStatus',
            ])
            ->whereKey($return->getKey())
            ->where('return_member_id', $member->getKey())
            ->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function create(MemberAccount $account, array $data): ReturnModel
    {
        $member = $this->member($account);

        return DB::transaction(function () use ($account, $member, $data): ReturnModel {
            $goodsReceive = $this->eligibleGoodsReceive($member, (int) $data['goods_receive_id'], true);
            $trx = $goodsReceive->trx;
            if (! $trx
                || (int) $trx->trx_buyer_id !== (int) $member->getKey()
                || ! in_array($trx->trx_buyer_type, ['distributor', 'agent', 'reseller'], true)
                || ! in_array($trx->trx_type, ['stock'], true)
                || ! in_array($trx->trx_status, ['received', 'completed'], true)) {
                throw new ProcessException('Penerimaan barang tidak berasal dari transaksi pembelian yang valid.');
            }
            if ((int) $trx->trx_is_preorder === 1) {
                throw new ProcessException('Pesanan PO tidak dapat diajukan retur.');
            }

            $address = $this->memberAddress($member, (int) $data['address_id']);
            $receivedAt = $goodsReceive->goods_receive_status_datetime
                ?? $goodsReceive->goods_receive_created_datetime;
            $returnMaxDays = $this->returnMaxDays();
            if (! $receivedAt || $receivedAt->lt(now()->subDays($returnMaxDays))) {
                throw new ProcessException(
                    "Batas pengajuan retur maksimal {$returnMaxDays} hari setelah barang diterima."
                );
            }

            $items = $this->validateReturnItems($goodsReceive, collect($data['items']));
            $now = now();
            $return = ReturnModel::query()->create([
                'return_code' => $this->documentCodeService->next(DocumentCodeService::RETURN),
                'return_goods_receive_id' => $goodsReceive->getKey(),
                'return_member_id' => $member->getKey(),
                'return_member_address_id' => $address->getKey(),
                'return_pickup_name' => $address->member_address_recipient,
                'return_pickup_phone' => $address->member_address_phone,
                'return_pickup_address' => $address->member_address_full,
                'return_pickup_province_id' => $address->member_address_province_id,
                'return_pickup_city_id' => $address->member_address_city_id,
                'return_pickup_district_id' => $address->member_address_district_id,
                'return_pickup_subdistrict_id' => $address->member_address_subdistrict_id,
                'return_description' => $data['description'],
                'return_attachment_image_url_json' => collect($data['image_urls'] ?? [])
                    ->map(fn (string $url): ?string => MediaUrl::canonicalPrivateUrl($url, 'member'))
                    ->values()
                    ->all(),
                'return_attachment_video_url' => MediaUrl::canonicalPrivateUrl(
                    $data['video_url'] ?? null,
                    'member',
                ),
                'return_status' => 'submitted',
                'return_shipping_cost_bearer' => 'member',
                'return_shipping_method' => $data['shipping_method'],
                'return_shipping_cost' => $this->shippingCost($data),
                'return_approved_by' => 0,
                'return_created_datetime' => $now,
            ]);
            $return->details()->createMany($items->map(fn (array $item): array => [
                'return_detail_goods_receive_detail_id' => $item['goods_receive_detail_id'],
                'return_detail_product_id' => $item['product_id'],
                'return_detail_qty' => $item['quantity'],
                'return_detail_reason' => $item['reason'],
            ])->all());

            ReturnStatusLog::query()->create([
                'return_status_log_return_id' => $return->getKey(),
                'return_status_log_status' => 'submitted',
                'return_status_log_note' => "Retur dibuat dari penerimaan {$goodsReceive->goods_receive_number} dan menunggu keputusan admin.",
                'return_status_log_created_by' => $member->getKey(),
                'return_status_log_created_datetime' => $now,
            ]);

            $return->load(['details.product', 'details.goodsReceiveDetail', 'goodsReceive.trx.details']);
            $this->fulfillmentService->prepareReturnToCompanyShipping(
                $return,
                [
                    ...$data,
                    'shipping_cost_bearer' => 'member',
                    'items' => $this->returnCompanyShippingItems($return),
                ],
            );

            return $this->returnDetail($account, $return);
        });
    }

    private function eligibleGoodsReceive(Member $member, int $goodsReceiveId, bool $lock = false): GoodsReceive
    {
        $query = GoodsReceive::query()
            ->with(['details.product', 'trx.details'])
            ->whereKey($goodsReceiveId)
            ->where('goods_receive_buyer_id', $member->getKey())
            ->where('goods_receive_status', 'completed');

        if ($lock) {
            $query->lockForUpdate();
        }

        $goodsReceive = $query->first();
        if (! $goodsReceive) {
            throw new ProcessException('Data penerimaan barang tidak ditemukan.');
        }
        if (! $goodsReceive->trx || (int) $goodsReceive->trx->trx_is_preorder === 1) {
            throw new ProcessException('Pesanan PO tidak dapat diajukan retur.');
        }
        $receivedAt = $goodsReceive->goods_receive_status_datetime
            ?? $goodsReceive->goods_receive_created_datetime;
        $returnMaxDays = $this->returnMaxDays();
        if (! $receivedAt || $receivedAt->lt(now()->subDays($returnMaxDays))) {
            throw new ProcessException(
                "Batas pengajuan retur maksimal {$returnMaxDays} hari setelah barang diterima."
            );
        }

        return $goodsReceive;
    }

    private function returnMaxDays(): int
    {
        return max(1, (int) BusinessConfig::get('return.return_max_days', 3));
    }

    private function shippingCost(array $data): int
    {
        return $data['shipping_method'] === 'pickup'
            ? 0
            : (int) data_get($data, 'courier.cost', 0);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function temporaryReturn(
        Member $member,
        GoodsReceive $goodsReceive,
        Collection $items,
        MemberAddress $address,
    ): ReturnModel {
        $return = new ReturnModel([
            'return_id' => 0,
            'return_goods_receive_id' => $goodsReceive->getKey(),
            'return_member_id' => $member->getKey(),
            'return_member_address_id' => $address->getKey(),
            'return_pickup_name' => $address->member_address_recipient,
            'return_pickup_phone' => $address->member_address_phone,
            'return_pickup_address' => $address->member_address_full,
            'return_pickup_province_id' => $address->member_address_province_id,
            'return_pickup_city_id' => $address->member_address_city_id,
            'return_pickup_district_id' => $address->member_address_district_id,
            'return_pickup_subdistrict_id' => $address->member_address_subdistrict_id,
            'return_status' => 'submitted',
        ]);
        $return->setRelation('goodsReceive', $goodsReceive);
        $return->setRelation('trx', $goodsReceive->trx);
        $return->setRelation('details', $items->map(function (array $item) use ($goodsReceive): ReturnDetail {
            /** @var GoodsReceiveDetail $goodsReceiveDetail */
            $goodsReceiveDetail = $goodsReceive->details
                ->firstWhere('goods_receive_detail_id', $item['goods_receive_detail_id']);
            $detail = new ReturnDetail([
                'return_detail_goods_receive_detail_id' => $goodsReceiveDetail->getKey(),
                'return_detail_product_id' => (int) $item['product_id'],
                'return_detail_qty' => (int) $item['quantity'],
                'return_detail_received_qty' => 0,
            ]);
            $detail->setRelation('product', $goodsReceiveDetail->product);
            $detail->setRelation('goodsReceiveDetail', $goodsReceiveDetail);

            return $detail;
        })->values());

        return $return;
    }

    private function memberAddress(Member $member, int $addressId): MemberAddress
    {
        $address = MemberAddress::query()
            ->whereKey($addressId)
            ->where('member_address_member_id', $member->getKey())
            ->first();
        if (! $address) {
            throw new ProcessException('Alamat pengiriman retur tidak ditemukan pada akun mitra.');
        }

        return $address;
    }

    /** @return list<array{product_id: int, quantity: int, batch_number: string, expiry_date: string}> */
    private function returnCompanyShippingItems(ReturnModel $return): array
    {
        return $return->details->map(function (ReturnDetail $detail): array {
            $goodsReceiveDetail = $detail->goodsReceiveDetail;

            return [
                'product_id' => (int) $detail->return_detail_product_id,
                'quantity' => (int) $detail->return_detail_qty,
                'batch_number' => (string) $goodsReceiveDetail?->goods_receive_detail_batch_number,
                'expiry_date' => (string) $goodsReceiveDetail?->goods_receive_detail_expire_date?->toDateString(),
            ];
        })->values()->all();
    }

    /** @param Collection<int, array<string, mixed>> $items
     * @return Collection<int, array<string, mixed>>
     */
    private function validateReturnItems(
        GoodsReceive $goodsReceive,
        Collection $items,
    ): Collection {
        $receivedDetails = $goodsReceive->details->keyBy('goods_receive_detail_id');
        $returnTable = (new ReturnModel)->getTable();
        $detailTable = (new ReturnDetail)->getTable();
        $returnedQuantities = ReturnDetail::query()
            ->join(
                $returnTable,
                "{$returnTable}.return_id",
                '=',
                "{$detailTable}.return_detail_return_id"
            )
            ->where("{$returnTable}.return_goods_receive_id", $goodsReceive->getKey())
            ->whereNotIn("{$returnTable}.return_status", ['rejected'])
            ->whereNotNull("{$detailTable}.return_detail_goods_receive_detail_id")
            ->groupBy("{$detailTable}.return_detail_goods_receive_detail_id")
            ->selectRaw("{$detailTable}.return_detail_goods_receive_detail_id AS goods_receive_detail_id")
            ->selectRaw("SUM({$detailTable}.return_detail_qty) AS returned_quantity")
            ->pluck('returned_quantity', 'goods_receive_detail_id');

        return $items->map(function (array $item) use ($receivedDetails, $returnedQuantities): array {
            $goodsReceiveDetailId = filled($item['goods_receive_detail_id'] ?? null)
                ? (int) $item['goods_receive_detail_id']
                : null;
            $productId = filled($item['product_id'] ?? null)
                ? (int) $item['product_id']
                : null;
            $batchNumber = trim((string) ($item['batch_number'] ?? ''));

            /** @var GoodsReceiveDetail|null $receivedDetail */
            $receivedDetail = $goodsReceiveDetailId !== null
                ? $receivedDetails->get($goodsReceiveDetailId)
                : $receivedDetails->first(function (GoodsReceiveDetail $detail) use ($productId, $batchNumber): bool {
                    if ($productId === null
                        || (int) $detail->goods_receive_detail_product_id !== $productId) {
                        return false;
                    }

                    return $batchNumber === ''
                        || hash_equals(
                            trim((string) $detail->goods_receive_detail_batch_number),
                            $batchNumber,
                        );
                });

            if (! $receivedDetail) {
                throw new ProcessException(
                    $goodsReceiveDetailId !== null
                        ? 'Batch produk retur tidak terdapat pada penerimaan barang yang dipilih.'
                        : 'Produk atau batch retur tidak terdapat pada penerimaan barang yang dipilih.'
                );
            }
            $quantity = (int) $item['quantity'];
            $received = (int) $receivedDetail->goods_receive_detail_qty;
            $goodsReceiveDetailId = (int) $receivedDetail->getKey();
            $returned = (int) $returnedQuantities->get($goodsReceiveDetailId, 0);
            $receivedBatchNumber = trim((string) $receivedDetail->goods_receive_detail_batch_number);

            if ($productId !== null
                && (int) $receivedDetail->goods_receive_detail_product_id !== $productId) {
                throw new ProcessException('Produk retur tidak sesuai dengan penerimaan barang yang dipilih.');
            }

            if ($batchNumber !== '' && ! hash_equals($receivedBatchNumber, $batchNumber)) {
                throw new ProcessException('Nomor batch produk retur tidak sesuai dengan batch penerimaan barang.');
            }

            if ($quantity > $received - $returned) {
                throw new ProcessException('Jumlah produk retur melebihi sisa batch yang dapat diretur.');
            }

            return [
                ...$item,
                'goods_receive_detail_id' => $goodsReceiveDetailId,
                'product_id' => (int) $receivedDetail->goods_receive_detail_product_id,
            ];
        });
    }

    private function member(MemberAccount $account): Member
    {
        $account->loadMissing('member');
        $member = $account->member;

        if (! $member || (int) $member->member_status !== 1) {
            throw new ProcessException('Data mitra aktif tidak ditemukan.', 403);
        }

        return $member;
    }
}
