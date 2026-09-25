<?php

namespace App\Http\Controllers\Api\V1\Member\SalesOrder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Sales\CreateSaleCustomerRequest;
use App\Http\Requests\Api\V1\Member\Sales\SearchSaleCustomersRequest;
use App\Models\MemberAccount;
use App\Services\Sales\MemberSalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CustomerController
 * Module: Member
 * Menu: SalesOrder / Customer
 */
class CustomerController extends Controller
{
    public function __construct(private readonly MemberSalesService $salesService) {}

    public function options(SearchSaleCustomersRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Pilihan pelanggan berhasil dimuat.',
            'data' => [
                'results' => $this->salesService->customerOptions(
                    $this->memberAccount($request),
                    $request->validated(),
                ),
            ],
        ]);
    }

    public function store(CreateSaleCustomerRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Pelanggan berhasil ditambahkan.',
            'data' => $this->salesService->createCustomer(
                $this->memberAccount($request),
                $request->validated(),
            ),
        ]);
    }

    private function memberAccount(Request $request): MemberAccount
    {
        $account = $request->user();
        abort_unless($account instanceof MemberAccount, 401);

        return $account;
    }
}
