<?php

namespace Database\Seeders;

use App\Models\BankCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InitialCompanyBankSeeder extends Seeder
{
    public function run(): void
    {
        $bankId = (int) DB::table('ref_bank')
            ->where('bank_is_active', 1)
            ->orderByRaw("CASE WHEN bank_code = 'BCA' THEN 0 ELSE 1 END")
            ->orderBy('bank_id')
            ->value('bank_id');

        if ($bankId === 0) {
            $bankId = 1;
        }

        $companyBank = BankCompany::query()->find(1) ?? new BankCompany;
        $companyBank->bank_company_id = 1;
        $companyBank->fill([
            'bank_company_type' => 'company',
            'bank_company_bank_id' => $bankId,
            'bank_company_bank_acc_name' => 'PT Deenye Berkah Abadi',
            'bank_company_bank_acc_number' => '880000000001',
            'bank_company_bank_is_active' => 1,
        ])->save();

        $spreadPaymentBank = BankCompany::query()->find(2) ?? new BankCompany;
        $spreadPaymentBank->bank_company_id = 2;
        $spreadPaymentBank->fill([
            'bank_company_type' => 'spread_payment',
            'bank_company_bank_id' => $bankId,
            'bank_company_bank_acc_name' => 'DNY Spread Payment',
            'bank_company_bank_acc_number' => '880000000002',
            'bank_company_bank_is_active' => 1,
        ])->save();
    }
}
