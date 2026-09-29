<?php

namespace App\Http\Controllers\Api\V1\Admin\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Report\ListReportsRequest;
use App\Http\Resources\Api\V1\Admin\AdminPaymentResource;
use App\Http\Resources\DataTableResource;
use App\Services\Report\AdminReportService;

class CashIncomeReportController extends Controller
{
    public function __construct(private readonly AdminReportService $reportService) {}

    public function index(ListReportsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->reportService->cashIncome($request->validated()),
            AdminPaymentResource::class,
        ))->additional(['success' => true, 'message' => 'Laporan penerimaan kas berhasil dimuat.']);
    }
}
