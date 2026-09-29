<?php

namespace App\Http\Controllers\Api\V1\Member\Reward;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Reward\ListSharingProfitsRequest;
use App\Http\Resources\Api\V1\Member\MemberSharingProfitResource;
use App\Http\Resources\DataTableResource;
use App\Models\MemberAccount;
use App\Models\TrxSpreadPayment;
use App\Services\Reward\MemberSharingProfitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SharingProfitController extends Controller
{
    public function __construct(private readonly MemberSharingProfitService $sharingProfitService) {}

    public function index(ListSharingProfitsRequest $request): DataTableResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        return (new DataTableResource(
            $this->sharingProfitService->sharingProfits(
                $account->member_account_member_id,
                $request->validated(),
            ),
            MemberSharingProfitResource::class,
        ))->additional([
            'success' => true,
            'message' => 'Daftar sharing profit berhasil dimuat.',
        ]);
    }

    public function show(TrxSpreadPayment $sharingProfit, Request $request): MemberSharingProfitResource|JsonResponse
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        if ($sharingProfit->trx_spread_payment_upline_id !== $account->member_account_member_id) {
            return response()->json([
                'success' => false,
                'message' => 'Sharing profit tidak ditemukan.',
                'error_code' => 'not_found',
            ], 404);
        }

        return (new MemberSharingProfitResource(
            $this->sharingProfitService->sharingProfit($sharingProfit),
        ))->additional([
            'success' => true,
            'message' => 'Detail sharing profit berhasil dimuat.',
        ]);
    }
}
