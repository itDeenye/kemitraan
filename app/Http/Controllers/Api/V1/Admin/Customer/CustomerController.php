<?php

namespace App\Http\Controllers\Api\V1\Admin\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Customer\ListCustomersRequest;
use App\Http\Resources\Api\V1\Admin\AdminCustomerResource;
use App\Http\Resources\DataTableResource;
use App\Models\Customer;
use App\Services\Customer\AdminCustomerService;

/**
 * CustomerController
 * Module: Admin
 * Menu: Customer
 */
class CustomerController extends Controller
{
    public function __construct(private readonly AdminCustomerService $customerService) {}

    public function index(ListCustomersRequest $request): DataTableResource
    {
        return (new DataTableResource($this->customerService->list($request->validated()), AdminCustomerResource::class))
            ->additional(['success' => true, 'message' => 'Daftar pelanggan berhasil dimuat.']);
    }

    public function show(Customer $customer): AdminCustomerResource
    {
        return (new AdminCustomerResource($this->customerService->detail($customer)))
            ->additional(['success' => true, 'message' => 'Detail pelanggan berhasil dimuat.']);
    }
}
