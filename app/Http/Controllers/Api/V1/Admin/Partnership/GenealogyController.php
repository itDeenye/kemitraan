<?php

namespace App\Http\Controllers\Api\V1\Admin\Partnership;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Partnership\ListGenealogyRequest;
use App\Services\Partnership\AdminMemberService;
use Illuminate\Http\JsonResponse;

class GenealogyController extends Controller
{
    public function __construct(private readonly AdminMemberService $memberService) {}

    public function index(ListGenealogyRequest $request): JsonResponse
    {
        $params = $request->validated();

        return response()->json([
            'success' => true,
            'message' => 'Pohon jaringan mitra berhasil dimuat.',
            'data' => [
                'results' => $this->memberService->genealogy(
                    isset($params['member_id']) ? (int) $params['member_id'] : null,
                    (int) ($params['depth'] ?? 3),
                ),
            ],
        ]);
    }
}
