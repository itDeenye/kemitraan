<?php

namespace App\Http\Controllers\Api\V1\Admin\Partnership;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Partnership\ListDistributorRequest;
use App\Http\Requests\Api\V1\Admin\Partnership\StoreDistributorRequest;
use App\Http\Resources\Api\V1\Admin\AdminDistributorResource;
use App\Http\Resources\DataTableResource;
use App\Models\Member;
use App\Services\Partnership\AdminDistributorService;
use Illuminate\Http\JsonResponse;

class DistributorController extends Controller
{
    public function __construct(private readonly AdminDistributorService $distributorService) {}

    public function index(ListDistributorRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->distributorService->list($request->validated()),
            AdminDistributorResource::class
        ))->additional(['success' => true, 'message' => 'Daftar distributor berhasil dimuat.']);
    }

    public function show(Member $distributor): AdminDistributorResource
    {
        return (new AdminDistributorResource($this->distributorService->detail($distributor)))
            ->additional(['success' => true, 'message' => 'Detail distributor berhasil dimuat.']);
    }

    public function store(StoreDistributorRequest $request): JsonResponse
    {
        $member = $this->distributorService->store($request->validated());

        return (new AdminDistributorResource($member))
            ->additional(['success' => true, 'message' => 'Distributor baru berhasil ditambahkan.'])
            ->response()
            ->setStatusCode(201);
    }
}
