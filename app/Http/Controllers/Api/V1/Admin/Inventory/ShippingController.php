<?php

namespace App\Http\Controllers\Api\V1\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Transaction\ListOrdersRequest;
use App\Http\Requests\Api\V1\Admin\Transaction\ListShipmentCouriersRequest;
use App\Http\Requests\Api\V1\Admin\Transaction\ShipOrderRequest;
use App\Http\Resources\Api\V1\Admin\AdminOrderResource;
use App\Http\Resources\DataTableResource;
use App\Models\Trx;
use App\Services\Shipping\AdminShippingService;
use App\Services\Transaction\AdminTransactionService;
use Illuminate\Http\JsonResponse;

class ShippingController extends Controller
{
    public function __construct(
        private readonly AdminTransactionService $transactionService,
        private readonly AdminShippingService $shippingService,
    ) {}

    public function index(ListOrdersRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->transactionService->orders($request->validated() + [
                '_statuses' => ['processing', 'shipped', 'reship_required', 'ready_to_pickup', 'received', 'completed'],
            ]),
            AdminOrderResource::class,
        ))->additional(['success' => true, 'message' => 'Daftar pengiriman barang berhasil dimuat.']);
    }

    public function show(Trx $trx): AdminOrderResource
    {
        return (new AdminOrderResource($this->transactionService->order($trx)))
            ->additional(['success' => true, 'message' => 'Detail pengiriman barang berhasil dimuat.']);
    }

    public function schedules(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Jadwal pengambilan paket berhasil dimuat.',
            'data' => ['results' => $this->shippingService->expressPickupSchedules()],
        ]);
    }

    public function couriers(ListShipmentCouriersRequest $request, Trx $trx): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Pilihan kurir pengiriman ulang berhasil dimuat.',
            'data' => $this->shippingService->expressCouriers($trx, $request->validated()),
        ]);
    }

    public function tracking(Trx $trx): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Tracking pengiriman berhasil dimuat dari STC.',
            'data' => $this->shippingService->trackExpress($trx),
        ]);
    }

    public function ship(ShipOrderRequest $request, Trx $trx): AdminOrderResource
    {
        return (new AdminOrderResource($this->shippingService->ship(
            $trx,
            $request->validated(),
        )))
            ->additional(['success' => true, 'message' => 'Pesanan berhasil ditandai sudah dikirim.']);
    }
}
