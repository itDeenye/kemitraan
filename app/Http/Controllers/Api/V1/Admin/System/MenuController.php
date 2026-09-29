<?php

namespace App\Http\Controllers\Api\V1\Admin\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\System\ListMenusRequest;
use App\Http\Requests\Api\V1\Admin\System\SaveMenuRequest;
use App\Http\Resources\Api\V1\Admin\AdminMenuManagementResource;
use App\Http\Resources\DataTableResource;
use App\Models\SiteAdministratorMenu;
use App\Services\System\AdminAccessControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MenuController extends Controller
{
    public function __construct(private readonly AdminAccessControlService $accessControlService) {}

    public function index(ListMenusRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->accessControlService->menus($request->validated()),
            AdminMenuManagementResource::class
        ))->additional(['success' => true, 'message' => 'Daftar menu administrator berhasil dimuat.']);
    }

    public function tree(): AnonymousResourceCollection
    {
        return AdminMenuManagementResource::collection($this->accessControlService->menuTree())
            ->additional(['success' => true, 'message' => 'Struktur menu administrator berhasil dimuat.']);
    }

    public function show(SiteAdministratorMenu $menu): AdminMenuManagementResource
    {
        return (new AdminMenuManagementResource($this->accessControlService->menu($menu)))
            ->additional(['success' => true, 'message' => 'Detail menu administrator berhasil dimuat.']);
    }

    public function store(SaveMenuRequest $request): AdminMenuManagementResource
    {
        return (new AdminMenuManagementResource(
            $this->accessControlService->createMenu($request->validated())
        ))->additional(['success' => true, 'message' => 'Menu administrator berhasil dibuat.']);
    }

    public function update(
        SaveMenuRequest $request,
        SiteAdministratorMenu $menu
    ): AdminMenuManagementResource {
        return (new AdminMenuManagementResource(
            $this->accessControlService->updateMenu($menu, $request->validated())
        ))->additional(['success' => true, 'message' => 'Menu administrator berhasil diperbarui.']);
    }

    public function destroy(SiteAdministratorMenu $menu): JsonResponse
    {
        $this->accessControlService->deleteMenu($menu);

        return response()->json([
            'success' => true,
            'message' => 'Menu administrator berhasil dihapus.',
            'data' => null,
        ]);
    }
}
