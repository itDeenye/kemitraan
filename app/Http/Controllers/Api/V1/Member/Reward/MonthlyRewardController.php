<?php

namespace App\Http\Controllers\Api\V1\Member\Reward;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Reward\ApproveDownlineMonthlyRewardRequest;
use App\Http\Requests\Api\V1\Member\Reward\ListDownlineMonthlyRewardsRequest;
use App\Http\Requests\Api\V1\Member\Reward\ListMonthlyRewardsRequest;
use App\Http\Resources\Api\V1\Member\MemberDownlineMonthlyRewardResource;
use App\Http\Resources\Api\V1\Member\MemberMonthlyRewardResource;
use App\Http\Resources\DataTableResource;
use App\Models\MemberAccount;
use App\Models\RewardPointMonthly;
use App\Services\Reward\MemberMonthlyRewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonthlyRewardController extends Controller
{
    public function __construct(private readonly MemberMonthlyRewardService $rewardService) {}

    public function index(ListMonthlyRewardsRequest $request): DataTableResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();
        $params = $request->validated();
        $memberId = $account->member_account_member_id;

        $results = $this->rewardService->monthlyRewards($memberId, $params);
        $summary = $this->rewardService->summary($memberId, $params);
        $summaryTotal = $this->rewardService->summary($memberId, []);

        return (new DataTableResource(
            $results,
            MemberMonthlyRewardResource::class,
        ))->additional([
            'success' => true,
            'message' => 'Daftar reward bulanan berhasil dimuat.',
            'summary' => [
                'total_akumulasi' => $summary['accumulated'],
                'total_dibayarkan' => $summary['paid'],
                'total_belum_dibayarkan' => $summary['unpaid'],
            ],
            'summary_total' => [
                'total_akumulasi' => $summaryTotal['accumulated'],
                'total_dibayarkan' => $summaryTotal['paid'],
                'total_belum_dibayarkan' => $summaryTotal['unpaid'],
            ],
            'available_years' => $this->rewardService->availableYears($memberId),
        ]);
    }

    public function downlines(ListDownlineMonthlyRewardsRequest $request): DataTableResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();
        $params = $request->validated();
        $memberId = (int) $account->member_account_member_id;
        $summary = $this->rewardService->downlineSummary($memberId, $params);

        return (new DataTableResource(
            $this->rewardService->downlineMonthlyRewards($memberId, $params),
            MemberDownlineMonthlyRewardResource::class,
        ))->additional([
            'success' => true,
            'message' => 'Daftar kewajiban reward bulanan mitra bawahan berhasil dimuat.',
            'summary' => [
                'total_akumulasi' => $summary['accumulated'],
                'total_dibayarkan' => $summary['paid'],
                'total_belum_dibayarkan' => $summary['unpaid'],
            ],
            'action_count' => $this->rewardService->downlineActionCount($memberId),
            'available_years' => $this->rewardService->availableDownlineYears($memberId),
        ]);
    }

    public function growth(ListMonthlyRewardsRequest $request): JsonResponse
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Pertumbuhan reward bulanan berhasil dimuat.',
            'data' => $this->rewardService->growth(
                (int) $account->member_account_member_id,
                $request->validated(),
            ),
        ]);
    }

    public function approveDownline(
        ApproveDownlineMonthlyRewardRequest $request,
        RewardPointMonthly $monthly_reward,
    ): MemberDownlineMonthlyRewardResource {
        /** @var MemberAccount $account */
        $account = $request->user();

        return (new MemberDownlineMonthlyRewardResource(
            $this->rewardService->approveDownlineReward(
                $monthly_reward,
                (int) $account->member_account_member_id,
            ),
        ))->additional([
            'success' => true,
            'message' => 'Pembayaran reward bulanan berhasil disetujui.',
        ]);
    }

    public function show(
        RewardPointMonthly $monthly_reward,
        Request $request,
    ): MemberMonthlyRewardResource|JsonResponse {
        /** @var MemberAccount $account */
        $account = $request->user();

        if ($monthly_reward->reward_point_monthly_member_id !== $account->member_account_member_id) {
            return response()->json([
                'success' => false,
                'message' => 'Reward bulanan tidak ditemukan.',
                'error_code' => 'not_found',
            ], 404);
        }

        return (new MemberMonthlyRewardResource($this->rewardService->monthlyReward($monthly_reward)))
            ->additional(['success' => true, 'message' => 'Detail reward bulanan berhasil dimuat.']);
    }
}
