<?php

namespace App\Http\Controllers\Api\V1\Admin\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\System\ListConfigsRequest;
use App\Http\Requests\Api\V1\Admin\System\UpdateCommissionConfigRequest;
use App\Http\Resources\Api\V1\Admin\AdminConfigResource;
use App\Http\Resources\DataTableResource;
use App\Models\Config;
use App\Services\System\AdminSystemService;

class CommissionConfigController extends Controller
{
    public function __construct(private readonly AdminSystemService $systemService) {}

    public function index(ListConfigsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->systemService->commissionConfigs($request->validated()),
            AdminConfigResource::class,
        ))->additional([
            'success' => true,
            'message' => 'Daftar konfigurasi komisi berhasil dimuat.',
        ]);
    }

    public function update(
        UpdateCommissionConfigRequest $request,
        Config $config,
    ): AdminConfigResource {
        return (new AdminConfigResource(
            $this->systemService->updateCommissionConfig($config, $request->validated()),
        ))->additional([
            'success' => true,
            'message' => 'Konfigurasi komisi berhasil diperbarui.',
        ]);
    }
}
