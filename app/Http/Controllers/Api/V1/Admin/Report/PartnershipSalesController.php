<?php

namespace App\Http\Controllers\Api\V1\Admin\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Report\ListPartnershipSalesRequest;
use App\Http\Resources\Api\V1\Admin\AdminOrderResource;
use App\Http\Resources\Api\V1\Admin\Report\AdminPartnershipSalesResource;
use App\Http\Resources\DataTableResource;
use App\Models\Trx;
use App\Services\Report\AdminPartnershipSalesService;
use App\Services\Transaction\AdminTransactionService;

class PartnershipSalesController extends Controller
{
    public function __construct(
        private readonly AdminPartnershipSalesService $salesService,
        private readonly AdminTransactionService $transactionService
    ) {}

    public function index(ListPartnershipSalesRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->salesService->sales($request->validated()),
            AdminPartnershipSalesResource::class,
        ))->additional(['success' => true, 'message' => 'Laporan penjualan mitra berhasil dimuat.']);
    }

    public function show(Trx $trx): AdminOrderResource
    {
        if (! in_array($trx->trx_buyer_type, ['distributor', 'agent', 'reseller', 'customer'], true)
            || ! in_array($trx->trx_seller_type, ['distributor', 'agent', 'reseller'], true)) {
            abort(404, 'Data transaksi tidak ditemukan.');
        }

        return (new AdminOrderResource($this->transactionService->order($trx)))
            ->additional(['success' => true, 'message' => 'Detail laporan penjualan mitra berhasil dimuat.']);
    }
}
