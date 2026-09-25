<?php

namespace App\Http\Controllers\Api\V1\Admin\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\System\ListAuditTrailsRequest;
use App\Http\Resources\Api\V1\Admin\AdminAuditTrailResource;
use App\Http\Resources\DataTableResource;
use App\Models\AuditTrail;
use App\Services\System\AdminSystemService;

class AuditTrailController extends Controller
{
    public function __construct(private readonly AdminSystemService $systemService) {}

    public function index(ListAuditTrailsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->systemService->auditTrails($request->validated()),
            AdminAuditTrailResource::class
        ))->additional(['success' => true, 'message' => 'Daftar audit trail berhasil dimuat.']);
    }

    public function show(AuditTrail $auditTrail): AdminAuditTrailResource
    {
        return (new AdminAuditTrailResource($this->systemService->auditTrail($auditTrail)))
            ->additional(['success' => true, 'message' => 'Detail audit trail berhasil dimuat.']);
    }
}
