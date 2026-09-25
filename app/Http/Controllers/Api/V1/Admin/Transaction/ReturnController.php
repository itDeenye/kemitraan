<?php

namespace App\Http\Controllers\Api\V1\Admin\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Transaction\ApproveReturnRequest;
use App\Http\Requests\Api\V1\Admin\Transaction\ListReturnsRequest;
use App\Http\Requests\Api\V1\Admin\Transaction\ReceiveReturnRequest;
use App\Http\Requests\Api\V1\Admin\Transaction\ReturnActionRequest;
use App\Http\Requests\Api\V1\Admin\Transaction\ReturnShippingOptionsRequest;
use App\Http\Requests\Api\V1\Admin\Transaction\ShipReturnReplacementRequest;
use App\Http\Resources\Api\V1\Admin\AdminReturnResource;
use App\Http\Resources\DataTableResource;
use App\Models\ReturnModel;
use App\Models\SiteAdministrator;
use App\Services\Return\ReturnFulfillmentService;
use App\Services\Transaction\AdminTransactionService;
use Illuminate\Http\JsonResponse;

class ReturnController extends Controller
{
    public function __construct(
        private readonly AdminTransactionService $transactionService,
        private readonly ReturnFulfillmentService $fulfillmentService,
    ) {}

    public function index(ListReturnsRequest $request): DataTableResource
    {
        return (new DataTableResource(
            $this->transactionService->returns($request->validated()),
            AdminReturnResource::class,
        ))->additional(['success' => true, 'message' => 'Daftar retur pembelian berhasil dimuat.']);
    }

    public function show(ReturnModel $return): AdminReturnResource
    {
        return (new AdminReturnResource($this->transactionService->returnDetail($return)))
            ->additional(['success' => true, 'message' => 'Detail retur pembelian berhasil dimuat.']);
    }

    public function couriers(
        ReturnShippingOptionsRequest $request,
        ReturnModel $return,
        string $referenceType,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => 'Pilihan kurir retur berhasil dimuat.',
            'data' => $this->fulfillmentService->courierOptions(
                $return,
                $referenceType,
                $request->validated('couriers', []),
                $request->validated('items', []),
            ),
        ]);
    }

    public function schedules(ReturnModel $return, string $referenceType): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Jadwal pengambilan barang retur berhasil dimuat.',
            'data' => $this->fulfillmentService->pickupSchedules($return, $referenceType),
        ]);
    }

    public function approve(ApproveReturnRequest $request, ReturnModel $return): AdminReturnResource
    {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();

        return (new AdminReturnResource($this->fulfillmentService->approve(
            $return,
            $administrator,
            $request->validated(),
        )))->additional(['success' => true, 'message' => 'Retur berhasil disetujui.']);
    }

    public function reject(ReturnActionRequest $request, ReturnModel $return): AdminReturnResource
    {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();

        return (new AdminReturnResource($this->fulfillmentService->reject(
            $return,
            $administrator,
            $request->validated('note'),
        )))->additional(['success' => true, 'message' => 'Retur pembelian berhasil ditolak.']);
    }

    public function receive(ReceiveReturnRequest $request, ReturnModel $return): AdminReturnResource
    {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();

        return (new AdminReturnResource($this->fulfillmentService->receive(
            $return,
            $administrator,
        )))->additional([
            'success' => true,
            'message' => 'Barang retur berhasil dikonfirmasi diterima oleh perusahaan.',
        ]);
    }

    public function shipReplacement(
        ShipReturnReplacementRequest $request,
        ReturnModel $return,
    ): AdminReturnResource {
        /** @var SiteAdministrator $administrator */
        $administrator = $request->user();

        return (new AdminReturnResource($this->fulfillmentService->shipReplacement(
            $return,
            $administrator,
            $request->validated(),
        )))->additional([
            'success' => true,
            'message' => 'Barang pengganti berhasil dikirim ke mitra.',
        ]);
    }
}
