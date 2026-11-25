<?php

namespace App\Http\Controllers\Api\V1\Admin\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Report\ListMemberRankingReportRequest;
use App\Http\Resources\Api\V1\Admin\Report\AdminMemberRankingResource;
use App\Http\Resources\DataTableResource;
use App\Services\Report\AdminMemberRankingReportService;

class MemberRankingReportController extends Controller
{
    public function __invoke(ListMemberRankingReportRequest $request, AdminMemberRankingReportService $service): DataTableResource
    {
        return (new DataTableResource($service->report($request->validated()), AdminMemberRankingResource::class))
            ->additional(['success' => true, 'message' => 'Laporan mitra berhasil dimuat.']);
    }
}
