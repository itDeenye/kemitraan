<?php

namespace App\Http\Controllers\Api\V1\Member\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\Account\StoreAddressRequest;
use App\Http\Requests\Api\V1\Member\Account\UpdateAddressRequest;
use App\Http\Resources\Api\V1\Member\MemberAddressResource;
use App\Http\Resources\ApiResourceCollection;
use App\Models\MemberAccount;
use App\Models\MemberAddress;
use App\Services\Account\MemberAccountService;
use Illuminate\Http\JsonResponse;

class AddressController extends Controller
{
    public function __construct(private readonly MemberAccountService $accountService) {}

    public function index(): ApiResourceCollection
    {
        /** @var MemberAccount $account */
        $account = request()->user();

        return MemberAddressResource::collection(
            $this->accountService->getAddresses($account->member_account_member_id)
        )->additional(['success' => true, 'message' => 'Daftar alamat berhasil diambil.']);
    }

    public function store(StoreAddressRequest $request): MemberAddressResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        return (new MemberAddressResource(
            $this->accountService->createAddress($account->member_account_member_id, $request->validated())
        ))->additional(['success' => true, 'message' => 'Alamat berhasil ditambahkan.']);
    }

    public function show(MemberAddress $address): MemberAddressResource
    {
        /** @var MemberAccount $account */
        $account = request()->user();

        if ($address->member_address_member_id !== $account->member_account_member_id) {
            abort(404, 'Alamat tidak ditemukan.');
        }

        return (new MemberAddressResource(
            $address->load(['province', 'city', 'district', 'subdistrict', 'country'])
        ))->additional(['success' => true, 'message' => 'Detail alamat berhasil diambil.']);
    }

    public function update(UpdateAddressRequest $request, MemberAddress $address): MemberAddressResource
    {
        /** @var MemberAccount $account */
        $account = $request->user();

        if ($address->member_address_member_id !== $account->member_account_member_id) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah alamat ini.');
        }

        $this->accountService->updateAddress($address, $request->validated());

        return (new MemberAddressResource(
            $address->fresh()->load(['province', 'city', 'district', 'subdistrict', 'country'])
        ))->additional(['success' => true, 'message' => 'Alamat berhasil diperbarui.']);
    }

    public function destroy(MemberAddress $address): JsonResponse
    {
        /** @var MemberAccount $account */
        $account = request()->user();

        if ($address->member_address_member_id !== $account->member_account_member_id) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus alamat ini.');
        }

        $this->accountService->deleteAddress($address);

        return response()->json([
            'success' => true,
            'message' => 'Alamat berhasil dihapus.',
            'data' => null,
        ]);
    }

    public function setDefault(MemberAddress $address): MemberAddressResource
    {
        /** @var MemberAccount $account */
        $account = request()->user();

        if ($address->member_address_member_id !== $account->member_account_member_id) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah alamat ini.');
        }

        $this->accountService->setAddressAsDefault($address);

        return (new MemberAddressResource(
            $address->fresh()->load(['province', 'city', 'district', 'subdistrict', 'country'])
        ))->additional(['success' => true, 'message' => 'Alamat utama berhasil diperbarui.']);
    }
}
