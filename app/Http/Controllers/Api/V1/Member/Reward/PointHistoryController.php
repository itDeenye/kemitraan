<?php

namespace App\Http\Controllers\Api\V1\Member\Reward;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Reward\ListPointHistoryRequest;
use App\Http\Resources\Api\V1\Member\MemberPointHistoryResource;
use App\Http\Resources\DataTableResource;
use App\Models\MemberAccount;
use App\Services\Reward\MemberPointHistoryService;

class PointHistoryController extends Controller
{
    public function __construct(private readonly MemberPointHistoryService $historyService) {}

    public function index(ListPointHistoryRequest $request): DataTableResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();
        $history = $this->historyService->history((int) $account->member_account_member_id, $request->validated());

        return (new DataTableResource($history['data'], MemberPointHistoryResource::class))->additional([
            'success' => true,
            'message' => 'Riwayat poin berhasil dimuat.',
            'summary' => $history['summary'],
            'available_years' => $history['available_years'],
        ]);
    }
}
