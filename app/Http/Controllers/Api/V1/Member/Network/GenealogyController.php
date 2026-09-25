<?php

namespace App\Http\Controllers\Api\V1\Member\Network;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Network\ListGenealogyRequest;
use App\Http\Requests\Api\V1\Member\Network\TotalDownlineRequest;
use App\Http\Resources\Api\V1\Member\MemberGenealogyListResource;
use App\Models\MemberAccount;
use App\Services\Partnership\MemberGenealogyService;
use Illuminate\Http\JsonResponse;

class GenealogyController extends Controller
{
    public function __construct(private readonly MemberGenealogyService $genealogyService) {}

    public function index(ListGenealogyRequest $request): MemberGenealogyListResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        return (new MemberGenealogyListResource(
            $this->genealogyService->list($account, $request->validated()),
        ))->additional([
            'success' => true,
            'message' => 'Daftar jaringan mitra berhasil dimuat.',
        ]);
    }

    public function totalDownlines(TotalDownlineRequest $request): JsonResponse
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Total jaringan mitra berhasil dimuat.',
            'data' => [
                'total_downlines' => $this->genealogyService->totalDownlines($account),
            ],
        ]);
    }
}
