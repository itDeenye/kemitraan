<?php

namespace App\Http\Controllers\Api\V1\Member\Reward;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Reward\AnnualRewardReportRequest;
use App\Http\Resources\Api\V1\Member\MemberAnnualRewardReportResource;
use App\Models\MemberAccount;
use App\Services\Reward\AnnualRewardReportService;

class AnnualRewardController extends Controller
{
    public function __construct(private readonly AnnualRewardReportService $rewardReportService) {}

    public function index(AnnualRewardReportRequest $request): MemberAnnualRewardReportResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();
        $year = (int) ($request->validated('year') ?? now()->year);

        $memberId = (int) $account->member_account_member_id;

        return (new MemberAnnualRewardReportResource(
            $this->rewardReportService->memberReport($memberId, $year),
        ))->additional([
            'success' => true,
            'message' => 'Poin reward tahunan berhasil dimuat.',
            'available_years' => $this->rewardReportService->memberAvailableYears($memberId),
        ]);
    }
}
