<?php

namespace App\Http\Controllers\Api\V1\Admin\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\System\ListMemberLevelsRequest;
use App\Http\Requests\Api\V1\Admin\System\SaveMemberLevelRequest;
use App\Http\Resources\Api\V1\Admin\AdminMemberLevelResource;
use App\Http\Resources\DataTableResource;
use App\Models\MemberLevel;
use App\Services\System\AdminSystemService;

class MemberLevelController extends Controller
{
    public function __construct(private readonly AdminSystemService $systemService) {}

    public function index(ListMemberLevelsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->systemService->memberLevels($request->validated()),
            AdminMemberLevelResource::class
        ))->additional(['success' => true, 'message' => 'Daftar tingkat mitra berhasil dimuat.']);
    }

    public function show(MemberLevel $memberLevel): AdminMemberLevelResource
    {
        return (new AdminMemberLevelResource($this->systemService->memberLevel($memberLevel)))
            ->additional(['success' => true, 'message' => 'Detail tingkat mitra berhasil dimuat.']);
    }

    public function update(
        SaveMemberLevelRequest $request,
        MemberLevel $memberLevel
    ): AdminMemberLevelResource {
        return (new AdminMemberLevelResource(
            $this->systemService->updateMemberLevel($memberLevel, $request->validated())
        ))->additional(['success' => true, 'message' => 'Tingkat mitra berhasil diperbarui.']);
    }
}
