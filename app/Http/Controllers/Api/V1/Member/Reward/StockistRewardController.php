<?php

namespace App\Http\Controllers\Api\V1\Member\Reward;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Reward\ListStockistRewardsRequest;
use App\Http\Resources\Api\V1\Member\MemberStockistRewardResource;
use App\Http\Resources\DataTableResource;
use App\Models\MemberAccount;
use App\Models\RewardStockist;
use App\Services\Reward\MemberStockistRewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockistRewardController extends Controller
{
    public function __construct(private readonly MemberStockistRewardService $rewardService) {}

    public function index(ListStockistRewardsRequest $request): DataTableResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();
        $params = $request->validated();
        $memberId = $account->member_account_member_id;

        $results = $this->rewardService->stockistRewards($memberId, $params);
        $summary = $this->rewardService->summary($memberId, $params);

        return (new DataTableResource(
            $results,
            MemberStockistRewardResource::class
        ))->additional([
            'success' => true,
            'message' => 'Daftar voucher reward stockist berhasil dimuat.',
            'summary' => [
                'total_pembelanjaan' => $summary['total_spending'],
                'total_voucher' => $summary['total_voucher'],
                'persentase_voucher' => $summary['percentage'],
            ],
            'available_years' => $this->rewardService->availableYears($memberId),
        ]);
    }

    public function show(RewardStockist $stockist_reward, Request $request): MemberStockistRewardResource|JsonResponse
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        if ($stockist_reward->reward_stockist_member_id !== $account->member_account_member_id) {
            return response()->json([
                'success' => false,
                'message' => 'Reward stockist tidak ditemukan.',
                'error_code' => 'not_found',
            ], 404);
        }

        return (new MemberStockistRewardResource($this->rewardService->stockistReward($stockist_reward)))
            ->additional(['success' => true, 'message' => 'Detail voucher reward stockist berhasil dimuat.']);
    }
}
