<?php

namespace App\Http\Controllers\Api\V1\Admin\Reward;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Reward\ListAnnualRewardsRequest;
use App\Http\Resources\Api\V1\Admin\AdminAnnualRewardReportResource;
use App\Http\Resources\DataTableResource;
use App\Models\RewardPointAnnual;
use App\Services\Reward\AnnualRewardReportService;

class AnnualRewardController extends Controller
{
    public function __construct(private readonly AnnualRewardReportService $rewardReportService) {}

    public function index(ListAnnualRewardsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->rewardReportService->reports($request->validated()),
            AdminAnnualRewardReportResource::class,
        ))->additional(['success' => true, 'message' => 'Laporan poin reward tahunan berhasil dimuat.']);
    }

    public function show(RewardPointAnnual $annualReward): AdminAnnualRewardReportResource
    {
        return (new AdminAnnualRewardReportResource($this->rewardReportService->report($annualReward)))
            ->additional(['success' => true, 'message' => 'Detail poin reward tahunan berhasil dimuat.']);
    }
}
