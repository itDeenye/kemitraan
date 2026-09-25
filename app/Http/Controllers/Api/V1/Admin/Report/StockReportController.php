<?php

namespace App\Http\Controllers\Api\V1\Admin\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Report\ListReportsRequest;
use App\Http\Resources\Api\V1\Admin\AdminStockReportResource;
use App\Http\Resources\DataTableResource;
use App\Services\Report\AdminReportService;

class StockReportController extends Controller
{
    public function __construct(private readonly AdminReportService $reportService) {}

    public function index(ListReportsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->reportService->stocks($request->validated()),
            AdminStockReportResource::class,
        ))->additional(['success' => true, 'message' => 'Laporan stok berhasil dimuat.']);
    }
}
