<?php

namespace App\Http\Controllers\Api\V1\Admin\Reward;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Reward\ListMonthlyRewardsRequest;
use App\Http\Resources\Api\V1\Admin\AdminMonthlyRewardResource;
use App\Http\Resources\DataTableResource;
use App\Models\RewardPointMonthly;
use App\Models\SiteAdministrator;
use App\Services\Reward\AdminMonthlyRewardService;
use Illuminate\Http\Request;

class MonthlyRewardController extends Controller
{
    public function __construct(private readonly AdminMonthlyRewardService $rewardService) {}

    public function index(ListMonthlyRewardsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->rewardService->monthlyRewards($request->validated()),
            AdminMonthlyRewardResource::class,
        ))->additional(['success' => true, 'message' => 'Daftar reward bulanan berhasil dimuat.']);
    }

    public function show(RewardPointMonthly $monthlyReward): AdminMonthlyRewardResource
    {
        return (new AdminMonthlyRewardResource($this->rewardService->monthlyReward($monthlyReward)))
            ->additional(['success' => true, 'message' => 'Detail reward bulanan berhasil dimuat.']);
    }

    public function process(Request $request, RewardPointMonthly $monthlyReward): AdminMonthlyRewardResource
    {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();

        return (new AdminMonthlyRewardResource(
            $this->rewardService->processMonthlyReward($monthlyReward, $administrator),
        ))->additional(['success' => true, 'message' => 'Reward bulanan Distributor berhasil diproses.']);
    }
}
