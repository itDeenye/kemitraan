<?php

namespace App\Http\Controllers\Api\V1\Admin\Reward;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Reward\ListStockistRewardsRequest;
use App\Http\Resources\Api\V1\Admin\AdminStockistRewardResource;
use App\Http\Resources\DataTableResource;
use App\Models\RewardStockist;
use App\Services\Reward\AdminMonthlyRewardService;

class StockistRewardController extends Controller
{
    public function __construct(private readonly AdminMonthlyRewardService $rewardService) {}

    public function index(ListStockistRewardsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->rewardService->stockistRewards($request->validated()),
            AdminStockistRewardResource::class,
        ))->additional(['success' => true, 'message' => 'Daftar reward stokis berhasil dimuat.']);
    }

    public function show(RewardStockist $stockistReward): AdminStockistRewardResource
    {
        return (new AdminStockistRewardResource($this->rewardService->stockistReward($stockistReward)))
            ->additional(['success' => true, 'message' => 'Detail reward stokis berhasil dimuat.']);
    }
}
