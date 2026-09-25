<?php

namespace App\Http\Controllers\Api\V1\Reference;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reference\ListCitiesRequest;
use App\Http\Requests\Api\V1\Reference\ListDistrictsRequest;
use App\Http\Requests\Api\V1\Reference\ListSubdistrictsRequest;
use App\Services\Reference\ReferenceService;
use Illuminate\Http\JsonResponse;

class ReferenceController extends Controller
{
    public function __construct(private readonly ReferenceService $referenceService) {}

    public function provinces(): JsonResponse
    {
        return $this->response(
            'Daftar provinsi berhasil dimuat.',
            $this->referenceService->provinces()
        );
    }

    public function cities(ListCitiesRequest $request): JsonResponse
    {
        return $this->response(
            'Daftar kota/kabupaten berhasil dimuat.',
            $this->referenceService->cities($request->validated('province_id'))
        );
    }

    public function districts(ListDistrictsRequest $request): JsonResponse
    {
        return $this->response(
            'Daftar kecamatan berhasil dimuat.',
            $this->referenceService->districts($request->validated('city_id'))
        );
    }

    public function subdistricts(ListSubdistrictsRequest $request): JsonResponse
    {
        return $this->response(
            'Daftar kelurahan/desa berhasil dimuat.',
            $this->referenceService->subdistricts($request->integer('district_id'))
        );
    }

    public function banks(): JsonResponse
    {
        return $this->response(
            'Daftar referensi bank berhasil dimuat.',
            $this->referenceService->banks()
        );
    }

    public function countries(): JsonResponse
    {
        return $this->response(
            'Daftar negara berhasil dimuat.',
            $this->referenceService->countries()
        );
    }

    private function response(string $message, mixed $data): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }
}
