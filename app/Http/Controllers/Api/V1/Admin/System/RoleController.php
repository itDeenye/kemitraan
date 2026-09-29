<?php

namespace App\Http\Controllers\Api\V1\Admin\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\System\ListRolesRequest;
use App\Http\Requests\Api\V1\Admin\System\SaveRolePrivilegesRequest;
use App\Http\Requests\Api\V1\Admin\System\SaveRoleRequest;
use App\Http\Resources\Api\V1\Admin\AdminRolePrivilegeResource;
use App\Http\Resources\Api\V1\Admin\AdminRoleResource;
use App\Http\Resources\DataTableResource;
use App\Models\SiteAdministratorGroup;
use App\Services\System\AdminAccessControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    public function __construct(private readonly AdminAccessControlService $accessControlService) {}

    public function index(ListRolesRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->accessControlService->roles($request->validated()),
            AdminRoleResource::class
        ))->additional(['success' => true, 'message' => 'Daftar grup akses administrator berhasil dimuat.']);
    }

    public function show(SiteAdministratorGroup $role): AdminRoleResource
    {
        return (new AdminRoleResource($this->accessControlService->role($role)))
            ->additional(['success' => true, 'message' => 'Detail grup akses administrator berhasil dimuat.']);
    }

    public function store(SaveRoleRequest $request): AdminRoleResource
    {
        return (new AdminRoleResource($this->accessControlService->createRole($request->validated())))
            ->additional(['success' => true, 'message' => 'Grup akses administrator berhasil dibuat.']);
    }

    public function update(
        SaveRoleRequest $request,
        SiteAdministratorGroup $role
    ): AdminRoleResource {
        return (new AdminRoleResource(
            $this->accessControlService->updateRole($role, $request->validated())
        ))->additional(['success' => true, 'message' => 'Grup akses administrator berhasil diperbarui.']);
    }

    public function destroy(SiteAdministratorGroup $role): JsonResponse
    {
        $this->accessControlService->deleteRole($role);

        return response()->json([
            'success' => true,
            'message' => 'Grup akses administrator berhasil dihapus.',
            'data' => null,
        ]);
    }

    public function privileges(SiteAdministratorGroup $role): AnonymousResourceCollection
    {
        return AdminRolePrivilegeResource::collection(
            $this->accessControlService->privileges($role)
        )->additional(['success' => true, 'message' => 'Hak akses grup berhasil dimuat.']);
    }

    public function syncPrivileges(
        SaveRolePrivilegesRequest $request,
        SiteAdministratorGroup $role
    ): AnonymousResourceCollection {
        return AdminRolePrivilegeResource::collection(
            $this->accessControlService->syncPrivileges($role, $request->validated('menus'))
        )->additional(['success' => true, 'message' => 'Hak akses grup berhasil diperbarui.']);
    }
}
