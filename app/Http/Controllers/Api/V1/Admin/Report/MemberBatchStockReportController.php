<?php

namespace App\Http\Controllers\Api\V1\Admin\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Report\ListMemberBatchStocksRequest;
use App\Http\Resources\Api\V1\Admin\AdminMemberBatchStockReportResource;
use App\Http\Resources\DataTableResource;
use App\Services\Report\AdminMemberBatchStockReportService;

class MemberBatchStockReportController extends Controller
{
    public function __construct(
        private readonly AdminMemberBatchStockReportService $reportService,
    ) {}

    public function index(ListMemberBatchStocksRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->reportService->list($request->validated()),
            AdminMemberBatchStockReportResource::class,
        ))->additional([
            'success' => true,
            'message' => 'Laporan stok per batch milik mitra berhasil dimuat.',
        ]);
    }
}
