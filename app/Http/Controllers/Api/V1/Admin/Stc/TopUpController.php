<?php

namespace App\Http\Controllers\Api\V1\Admin\Stc;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Stc\ListStcTransactionsRequest;
use App\Services\Stc\AdminStcService;
use Illuminate\Http\JsonResponse;

class TopUpController extends Controller
{
    public function __construct(private readonly AdminStcService $stcService) {}

    public function index(ListStcTransactionsRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Riwayat isi saldo pengiriman berhasil dimuat.',
            'data' => $this->stcService->topUps($request->validated()),
        ]);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Pilihan rekening virtual untuk isi saldo berhasil dimuat.',
            'data' => $this->stcService->topUpOptions(),
        ]);
    }
}
