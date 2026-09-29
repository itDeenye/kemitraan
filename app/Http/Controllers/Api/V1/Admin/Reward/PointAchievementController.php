<?php

namespace App\Http\Controllers\Api\V1\Admin\Reward;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Reward\ListPointAchievementsRequest;
use App\Http\Resources\Api\V1\Admin\AdminPointAchievementResource;
use App\Http\Resources\DataTableResource;
use App\Services\Reward\AdminMonthlyRewardService;

class PointAchievementController extends Controller
{
    public function __construct(private readonly AdminMonthlyRewardService $rewardService) {}

    public function index(ListPointAchievementsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->rewardService->pointAchievements($request->validated()),
            AdminPointAchievementResource::class,
        ))->additional(['success' => true, 'message' => 'Laporan pencapaian poin berhasil dimuat.']);
    }
}
