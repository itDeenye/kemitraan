<?php

namespace App\Http\Controllers\Api\V1\Member\Return;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Return\ConfirmReturnReplacementRequest;
use App\Http\Requests\Api\V1\Member\Return\ListMemberReturnsRequest;
use App\Http\Requests\Api\V1\Member\Return\ReturnShippingOptionsRequest;
use App\Http\Requests\Api\V1\Member\Return\ShipMemberReturnRequest;
use App\Http\Requests\Api\V1\Member\Return\StoreMemberReturnRequest;
use App\Http\Resources\Api\V1\Member\EligibleReturnReceiptResource;
use App\Http\Resources\Api\V1\Member\MemberReturnResource;
use App\Http\Resources\DataTableResource;
use App\Models\MemberAccount;
use App\Models\ReturnModel;
use App\Services\Return\MemberReturnService;
use App\Services\Return\ReturnFulfillmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    public function __construct(
        private readonly MemberReturnService $returnService,
        private readonly ReturnFulfillmentService $fulfillmentService,
    ) {}

    public function eligibleReceipts(ListMemberReturnsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->returnService->eligibleReceipts(
                $this->memberAccount($request),
                $request->validated()
            ),
            EligibleReturnReceiptResource::class,
        ))->additional([
            'success' => true,
            'message' => 'Daftar penerimaan yang dapat diretur berhasil dimuat.',
        ]);
    }

    public function index(ListMemberReturnsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->returnService->returns(
                $this->memberAccount($request),
                $request->validated()
            ),
            MemberReturnResource::class,
        ))->additional([
            'success' => true,
            'message' => 'Riwayat retur berhasil dimuat.',
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Ringkasan aksi retur berhasil dimuat.',
            'data' => $this->returnService->actionSummary($this->memberAccount($request)),
        ]);
    }

    public function couriers(ReturnShippingOptionsRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Pilihan kurir retur berhasil dimuat.',
            'data' => $this->returnService->returnCourierOptions(
                $this->memberAccount($request),
                $request->validated(),
            ),
        ]);
    }

    public function schedules(ReturnShippingOptionsRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Jadwal pengiriman retur berhasil dimuat.',
            'data' => $this->returnService->returnPickupSchedules(
                $this->memberAccount($request),
                $request->validated(),
            ),
        ]);
    }

    public function show(Request $request, ReturnModel $return): MemberReturnResource
    {
        return (new MemberReturnResource(
            $this->returnService->returnDetail($this->memberAccount($request), $return)
        ))->additional([
            'success' => true,
            'message' => 'Detail retur berhasil dimuat.',
        ]);
    }

    public function shippingSchedules(Request $request, ReturnModel $return): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Jadwal pickup kurir untuk pengiriman retur berhasil dimuat.',
            'data' => $this->fulfillmentService->memberPickupSchedules(
                $return,
                $this->memberAccount($request),
            ),
        ]);
    }

    public function store(StoreMemberReturnRequest $request): MemberReturnResource
    {
        return (new MemberReturnResource(
            $this->returnService->create(
                $this->memberAccount($request),
                $request->validated()
            )
        ))->additional([
            'success' => true,
            'message' => 'Pengajuan retur berhasil dibuat dan menunggu keputusan admin.',
        ]);
    }

    public function ship(
        ShipMemberReturnRequest $request,
        ReturnModel $return,
    ): MemberReturnResource {
        return (new MemberReturnResource($this->fulfillmentService->shipReturnToCompany(
            $return,
            $this->memberAccount($request),
            $request->validated(),
        )))->additional([
            'success' => true,
            'message' => 'Jadwal pengiriman retur berhasil dipilih dan permintaan pengiriman berhasil dibuat.',
        ]);
    }

    public function complete(
        ConfirmReturnReplacementRequest $request,
        ReturnModel $return,
    ): MemberReturnResource {
        return (new MemberReturnResource($this->fulfillmentService->complete(
            $return,
            $this->memberAccount($request),
            $request->validated(),
        )))->additional([
            'success' => true,
            'message' => 'Penerimaan barang pengganti berhasil dikonfirmasi.',
        ]);
    }

    private function memberAccount(Request $request): MemberAccount
    {
        $account = $request->user();

        abort_unless($account instanceof MemberAccount, 401);

        return $account;
    }
}
