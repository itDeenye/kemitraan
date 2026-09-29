<?php

namespace App\Http\Controllers\Api\V1\Member\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Account\StoreBankRequest;
use App\Http\Requests\Api\V1\Member\Account\UpdateBankRequest;
use App\Http\Resources\Api\V1\Member\MemberBankAccountResource;
use App\Http\Resources\ApiResourceCollection;
use App\Models\MemberAccount;
use App\Models\MemberBankAccount;
use App\Services\Account\MemberAccountService;
use Illuminate\Http\JsonResponse;

class BankController extends Controller
{
    public function __construct(private readonly MemberAccountService $accountService) {}

    public function index(): ApiResourceCollection
    {
        /** @var MemberAccount $account */
        $account = request()->user();

        return MemberBankAccountResource::collection(
            $this->accountService->getBanks($account->member_account_member_id)
        )->additional(['success' => true, 'message' => 'Daftar rekening berhasil diambil.']);
    }

    public function store(StoreBankRequest $request): MemberBankAccountResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        $bank = $this->accountService->createBank($account->member_account_member_id, $request->validated());

        return (new MemberBankAccountResource($bank))
            ->additional(['success' => true, 'message' => 'Rekening berhasil ditambahkan.']);
    }

    public function show(MemberBankAccount $bank): MemberBankAccountResource
    {
        /** @var MemberAccount $account */
        $account = request()->user();

        if ($bank->member_bank_account_member_id !== $account->member_account_member_id) {
            abort(404, 'Rekening tidak ditemukan.');
        }

        return (new MemberBankAccountResource($bank->load('bank')))
            ->additional(['success' => true, 'message' => 'Detail rekening berhasil diambil.']);
    }

    public function update(UpdateBankRequest $request, MemberBankAccount $bank): MemberBankAccountResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        if ($bank->member_bank_account_member_id !== $account->member_account_member_id) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah rekening ini.');
        }

        $this->accountService->updateBank($bank, $request->validated());

        return (new MemberBankAccountResource($bank->fresh()->load('bank')))
            ->additional(['success' => true, 'message' => 'Rekening berhasil diperbarui.']);
    }

    public function destroy(MemberBankAccount $bank): JsonResponse
    {
        /** @var MemberAccount $account */
        $account = request()->user();

        if ($bank->member_bank_account_member_id !== $account->member_account_member_id) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus rekening ini.');
        }

        $this->accountService->deleteBank($bank);

        return response()->json([
            'success' => true,
            'message' => 'Rekening berhasil dihapus.',
            'data' => null,
        ]);
    }

    public function setDefault(MemberBankAccount $bank): MemberBankAccountResource
    {
        /** @var MemberAccount $account */
        $account = request()->user();

        if ($bank->member_bank_account_member_id !== $account->member_account_member_id) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah rekening ini.');
        }

        $this->accountService->setBankAsDefault($bank);

        return (new MemberBankAccountResource($bank->fresh()->load('bank')))
            ->additional(['success' => true, 'message' => 'Rekening utama berhasil diperbarui.']);
    }
}
