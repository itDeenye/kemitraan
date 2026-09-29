<?php

namespace App\Http\Controllers\Api\V1\Admin\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Transaction\ListOrdersRequest;
use App\Http\Requests\Api\V1\Admin\Transaction\StockScreeningActionRequest;
use App\Http\Resources\Api\V1\Admin\AdminOrderResource;
use App\Http\Resources\DataTableResource;
use App\Models\Trx;
use App\Services\Inventory\StockScreeningService;
use App\Services\Transaction\AdminTransactionService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly AdminTransactionService $transactionService,
        private readonly StockScreeningService $stockScreeningService,
    ) {}

    public function index(ListOrdersRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->transactionService->orders($request->validated()),
            AdminOrderResource::class,
        ))->additional(['success' => true, 'message' => 'Daftar pesanan penjualan berhasil dimuat.']);
    }

    public function summary(ListOrdersRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Ringkasan pesanan penjualan berhasil dimuat.',
            'data' => $this->transactionService->orderSummary($request->validated()),
        ]);
    }

    public function show(Trx $trx): AdminOrderResource
    {
        return (new AdminOrderResource($this->transactionService->order($trx)))
            ->additional(['success' => true, 'message' => 'Detail pesanan penjualan berhasil dimuat.']);
    }

    public function document(Trx $trx): AdminOrderResource
    {
        return (new AdminOrderResource($this->transactionService->order($trx)))
            ->additional(['success' => true, 'message' => 'Data dokumen pesanan berhasil dimuat.']);
    }

    public function approveStockScreening(
        Trx $trx,
    ): AdminOrderResource {
        $trx = $this->stockScreeningService->approve($trx);

        return (new AdminOrderResource($this->transactionService->order($trx)))
            ->additional([
                'success' => true,
                'message' => 'Screening stok disetujui. Mitra sekarang dapat mengunggah bukti pembayaran.',
            ]);
    }

    public function rejectStockScreening(
        StockScreeningActionRequest $request,
        Trx $trx,
    ): AdminOrderResource {
        $trx = $this->stockScreeningService->reject(
            $trx,
            $request->validated('note'),
        );

        return (new AdminOrderResource($this->transactionService->order($trx)))
            ->additional([
                'success' => true,
                'message' => 'Screening stok ditolak. Pesanan beserta seluruh rangkaian terkait dibatalkan dan reservasi stok perusahaan telah dilepas.',
            ]);
    }
}
