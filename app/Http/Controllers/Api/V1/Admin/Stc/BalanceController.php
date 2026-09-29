<?php

namespace App\Http\Controllers\Api\V1\Admin\Stc;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Stc\ListStcTransactionsRequest;
use App\Services\Stc\AdminStcService;
use Illuminate\Http\JsonResponse;

class BalanceController extends Controller
{
    public function __construct(private readonly AdminStcService $stcService) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Saldo pengiriman berhasil dimuat.',
            'data' => $this->stcService->balance(),
        ]);
    }

    public function mutations(ListStcTransactionsRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Riwayat perubahan saldo pengiriman berhasil dimuat.',
            'data' => $this->stcService->mutations($request->validated()),
        ]);
    }
}
