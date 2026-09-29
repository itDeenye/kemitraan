<?php

namespace App\Http\Controllers\Api\V1\Admin\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Report\ListReportsRequest;
use App\Http\Resources\Api\V1\Admin\AdminOrderResource;
use App\Http\Resources\DataTableResource;
use App\Services\Report\AdminReportService;

class SalesReportController extends Controller
{
    public function __construct(private readonly AdminReportService $reportService) {}

    public function index(ListReportsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->reportService->sales($request->validated()),
            AdminOrderResource::class,
        ))->additional(['success' => true, 'message' => 'Laporan penjualan berhasil dimuat.']);
    }
}
