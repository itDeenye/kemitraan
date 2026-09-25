<?php

namespace App\Http\Controllers\Api\V1\Member\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Dashboard\DashboardRequest;
use App\Models\MemberAccount;
use App\Services\Dashboard\MemberDashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly MemberDashboardService $dashboardService) {}

    public function index(DashboardRequest $request): JsonResponse
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan beranda mitra berhasil dimuat.',
            'data' => $this->dashboardService->dashboard(
                $account->member_account_member_id,
                $request->validated(),
            ),
        ]);
    }
}
