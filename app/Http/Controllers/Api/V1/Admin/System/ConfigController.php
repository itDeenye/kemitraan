<?php

namespace App\Http\Controllers\Api\V1\Admin\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\System\ListConfigsRequest;
use App\Http\Requests\Api\V1\Admin\System\SaveConfigRequest;
use App\Http\Resources\Api\V1\Admin\AdminConfigResource;
use App\Http\Resources\DataTableResource;
use App\Models\Config;
use App\Services\System\AdminSystemService;

class ConfigController extends Controller
{
    public function __construct(private readonly AdminSystemService $systemService) {}

    public function index(ListConfigsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->systemService->configs($request->validated()),
            AdminConfigResource::class
        ))->additional(['success' => true, 'message' => 'Daftar konfigurasi sistem berhasil dimuat.']);
    }

    public function show(Config $config): AdminConfigResource
    {
        return (new AdminConfigResource($this->systemService->config($config)))
            ->additional(['success' => true, 'message' => 'Detail konfigurasi sistem berhasil dimuat.']);
    }

    public function update(SaveConfigRequest $request, Config $config): AdminConfigResource
    {
        return (new AdminConfigResource(
            $this->systemService->updateConfig($config, $request->validated())
        ))->additional(['success' => true, 'message' => 'Konfigurasi sistem berhasil diperbarui.']);
    }
}
