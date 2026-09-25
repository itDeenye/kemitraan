<?php

namespace App\Http\Controllers\Api\V1\Admin\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Dashboard\DashboardAnalyticsRequest;
use App\Services\Dashboard\AdminActionSummaryService;
use App\Services\Dashboard\AdminDashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $dashboardService,
        private readonly AdminActionSummaryService $actionSummaryService,
    ) {}

    public function actionSummary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Ringkasan tindakan admin berhasil dimuat.',
            'data' => $this->actionSummaryService->summary(),
        ]);
    }

    public function index(DashboardAnalyticsRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Analitik dashboard berhasil dimuat.',
            'data' => $this->dashboardService->analytics($request->validated()),
        ]);
    }

    public function statistics(DashboardAnalyticsRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Statistik operasional berhasil dimuat.',
            'data' => $this->dashboardService->analytics($request->validated()),
        ]);
    }
}
