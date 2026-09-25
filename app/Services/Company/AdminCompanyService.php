<?php

namespace App\Services\Company;

use App\Exceptions\ProcessException;
use App\Libraries\DataTable;
use App\Models\BankCompany;
use App\Models\GoodsReceive;
use App\Models\Trx;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Collection;

class AdminCompanyService
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function banks(array $params): array
    {
        return DataTable::select($this->bankColumns())
            ->from('bank_company')
            ->leftJoin('ref_bank', 'ref_bank.bank_id = bank_company.bank_company_bank_id')
            ->search(['bank_code', 'bank_name', 'account_name', 'account_number'])
            ->defaultSort('-id')
            ->get($params);
    }

    public function bank(BankCompany $bank): BankCompany
    {
        return BankCompany::query()
            ->select($this->bankColumns())
            ->leftJoin('ref_bank', 'ref_bank.bank_id', '=', 'bank_company.bank_company_bank_id')
            ->findOrFail($bank->getKey());
    }

    /** @param array<string, mixed> $data */
    public function createBank(array $data): BankCompany
    {
        $bank = BankCompany::query()->create($this->bankAttributes($data) + [
            'bank_company_bank_is_active' => true,
        ]);

        return $this->bank($bank);
    }

    /** @param array<string, mixed> $data */
    public function updateBank(BankCompany $bank, array $data): BankCompany
    {
        $bank->update($this->bankAttributes($data));

        return $this->bank($bank->refresh());
    }

    public function deleteBank(BankCompany $bank): void
    {
        $bank->delete();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function warehouses(array $params): array
    {
        return DataTable::select($this->warehouseColumns())
            ->from('warehouse')
            ->leftJoin('ref_province', 'ref_province.province_id = warehouse.warehouse_province_id')
            ->leftJoin('ref_city', 'ref_city.city_id = warehouse.warehouse_city_id')
            ->leftJoin('ref_district', 'ref_district.district_id = warehouse.warehouse_district_id')
            ->leftJoin('ref_subdistrict', 'ref_subdistrict.subdistrict_id = warehouse.warehouse_subdistrict_id')
            ->search(['name', 'legal_name', 'npwp', 'phone', 'email', 'address', 'city_name'])
            ->defaultSort('-id')
            ->get($params);
    }

    public function warehouse(Warehouse $warehouse): Warehouse
    {
        return Warehouse::query()
            ->select($this->warehouseColumns())
            ->leftJoin('ref_province', 'ref_province.province_id', '=', 'warehouse.warehouse_province_id')
            ->leftJoin('ref_city', 'ref_city.city_id', '=', 'warehouse.warehouse_city_id')
            ->leftJoin('ref_district', 'ref_district.district_id', '=', 'warehouse.warehouse_district_id')
            ->leftJoin('ref_subdistrict', 'ref_subdistrict.subdistrict_id', '=', 'warehouse.warehouse_subdistrict_id')
            ->findOrFail($warehouse->getKey());
    }

    /** @param array<string, mixed> $data */
    public function createWarehouse(array $data): Warehouse
    {
        $warehouse = Warehouse::query()->create($this->warehouseAttributes($data) + [
            'warehouse_is_active' => true,
            'warehouse_created_datetime' => now(),
        ]);

        return $this->warehouse($warehouse);
    }

    /** @param array<string, mixed> $data */
    public function updateWarehouse(Warehouse $warehouse, array $data): Warehouse
    {
        $warehouse->update($this->warehouseAttributes($data));

        return $this->warehouse($warehouse->refresh());
    }

    public function deleteWarehouse(Warehouse $warehouse): void
    {
        $warehouseId = $warehouse->getKey();
        $isUsed = WarehouseStock::query()
            ->where('warehouse_stock_warehouse_id', $warehouseId)
            ->exists()
            || Trx::query()
                ->where('trx_seller_type', 'warehouse')
                ->where('trx_seller_id', $warehouseId)
                ->exists()
            || GoodsReceive::query()
                ->where('goods_receive_seller_type', 'warehouse')
                ->where('goods_receive_seller_id', $warehouseId)
                ->exists();

        if ($isUsed) {
            throw new ProcessException('Gudang masih digunakan pada stok atau transaksi dan tidak dapat dihapus.');
        }

        $warehouse->delete();
    }

    /** @param array<string, mixed> $data */
    private function bankAttributes(array $data): array
    {
        $attributes = [
            'bank_company_type' => $data['type'],
            'bank_company_bank_id' => $data['bank_id'],
            'bank_company_bank_acc_name' => $data['account_name'],
            'bank_company_bank_acc_number' => $data['account_number'],
        ];

        if (array_key_exists('is_active', $data)) {
            $attributes['bank_company_bank_is_active'] = $data['is_active'];
        }

        return $attributes;
    }

    /** @param array<string, mixed> $data */
    private function warehouseAttributes(array $data): array
    {
        $attributes = [
            'warehouse_name' => $data['name'],
            'warehouse_legal_name' => $data['legal_name'],
            'warehouse_npwp' => $data['npwp'] ?? '',
            'warehouse_phone' => $data['phone'] ?? '',
            'warehouse_email' => $data['email'] ?? '',
            'warehouse_logo' => $data['logo_url'] ?? '',
            'warehouse_address' => $data['address'],
            'warehouse_province_id' => $data['province_id'],
            'warehouse_city_id' => $data['city_id'],
            'warehouse_district_id' => $data['district_id'],
            'warehouse_subdistrict_id' => $data['subdistrict_id'],
            'warehouse_latitude' => $data['latitude'] ?? null,
            'warehouse_longitude' => $data['longitude'] ?? null,
        ];

        if (array_key_exists('is_active', $data)) {
            $attributes['warehouse_is_active'] = $data['is_active'];
        }

        return $attributes;
    }

    /** @return array<int, string> */
    private function bankColumns(): array
    {
        return [
            'bank_company.bank_company_id as id',
            'bank_company.bank_company_type as type',
            'ref_bank.bank_id as bank_id',
            'ref_bank.bank_code as bank_code',
            'ref_bank.bank_name as bank_name',
            'ref_bank.bank_logo as bank_logo',
            'bank_company.bank_company_bank_acc_name as account_name',
            'bank_company.bank_company_bank_acc_number as account_number',
            'bank_company.bank_company_bank_is_active as is_active',
        ];
    }

    /** @return array<int, string> */
    private function warehouseColumns(): array
    {
        return [
            'warehouse.warehouse_id as id',
            'warehouse.warehouse_name as name',
            'warehouse.warehouse_legal_name as legal_name',
            'warehouse.warehouse_npwp as npwp',
            'warehouse.warehouse_phone as phone',
            'warehouse.warehouse_email as email',
            'warehouse.warehouse_logo as logo',
            'warehouse.warehouse_address as address',
            'warehouse.warehouse_province_id as province_id',
            'ref_province.province_name as province_name',
            'warehouse.warehouse_city_id as city_id',
            'ref_city.city_name as city_name',
            'ref_city.city_type as city_type',
            'warehouse.warehouse_district_id as district_id',
            'ref_district.district_name as district_name',
            'warehouse.warehouse_subdistrict_id as subdistrict_id',
            'ref_subdistrict.subdistrict_name as subdistrict_name',
            'warehouse.warehouse_latitude as latitude',
            'warehouse.warehouse_longitude as longitude',
            'warehouse.warehouse_is_active as is_active',
            'warehouse.warehouse_created_datetime as created_at',
        ];
    }
}
