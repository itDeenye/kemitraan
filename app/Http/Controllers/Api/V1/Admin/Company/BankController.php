<?php

namespace App\Http\Controllers\Api\V1\Admin\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Company\ListCompanyBanksRequest;
use App\Http\Requests\Api\V1\Admin\Company\SaveCompanyBankRequest;
use App\Http\Resources\Api\V1\Admin\AdminCompanyBankResource;
use App\Http\Resources\DataTableResource;
use App\Models\BankCompany;
use App\Services\Company\AdminCompanyService;
use Illuminate\Http\JsonResponse;

/**
 * BankController
 * Module: Admin
 * Menu: Company
 * Submenu: Bank
 */
class BankController extends Controller
{
    public function __construct(private readonly AdminCompanyService $companyService) {}

    public function index(ListCompanyBanksRequest $request): DataTableResource
    {
        return (new DataTableResource($this->companyService->banks($request->validated()), AdminCompanyBankResource::class))
            ->additional(['success' => true, 'message' => 'Daftar bank perusahaan berhasil dimuat.']);
    }

    public function show(BankCompany $companyBank): AdminCompanyBankResource
    {
        return (new AdminCompanyBankResource($this->companyService->bank($companyBank)))
            ->additional(['success' => true, 'message' => 'Detail bank perusahaan berhasil dimuat.']);
    }

    public function store(SaveCompanyBankRequest $request): AdminCompanyBankResource
    {
        return (new AdminCompanyBankResource(
            $this->companyService->createBank($request->validated())
        ))->additional(['success' => true, 'message' => 'Bank perusahaan berhasil dibuat.']);
    }

    public function update(
        SaveCompanyBankRequest $request,
        BankCompany $companyBank
    ): AdminCompanyBankResource {
        return (new AdminCompanyBankResource(
            $this->companyService->updateBank($companyBank, $request->validated())
        ))->additional(['success' => true, 'message' => 'Bank perusahaan berhasil diperbarui.']);
    }

    public function destroy(BankCompany $companyBank): JsonResponse
    {
        $this->companyService->deleteBank($companyBank);

        return response()->json([
            'success' => true,
            'message' => 'Bank perusahaan berhasil dihapus.',
            'data' => null,
        ]);
    }
}
