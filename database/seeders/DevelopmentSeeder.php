<?php

namespace Database\Seeders;

use App\Models\BankCompany;
use App\Models\Customer;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberAddress;
use App\Models\MemberBankAccount;
use App\Models\MemberLevel;
use App\Models\MemberStock;
use App\Models\MemberStockLog;
use App\Models\Product;
use App\Models\SiteAdministrator;
use App\Models\Trx;
use App\Models\TrxSpreadPayment;
use App\Services\Partnership\MemberCodeService;
use App\Services\Transaction\TransactionCodeService;
use App\Support\BusinessConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;

class DevelopmentSeeder extends Seeder
{
    private TransactionCodeService $transactionCodeService;

    public function run(
        MemberCodeService $memberCodeService,
        TransactionCodeService $transactionCodeService,
    ): void {
        if (! app()->environment(['local', 'development'])) {
            $this->command?->warn('DevelopmentSeeder hanya dapat dijalankan pada environment local/development.');

            return;
        }

        $this->assertCanRun();
        $this->transactionCodeService = $transactionCodeService;
        $member = Member::query()->find(1);
        $products = Product::query()
            ->where('product_is_deleted', 0)
            ->orderBy('product_id')
            ->limit(3)
            ->get();

        if (! $member || ($products->isEmpty())) {
            $this->command?->warn(
                'Data development tidak dibuat. Pastikan member awal dan katalog produk sudah tersedia.',
            );

            return;
        }

        DB::transaction(function () use ($member, $memberCodeService, $products): void {
            $administrator = SiteAdministrator::query()->find(1);
            $now = CarbonImmutable::now(config('app.timezone'));
            $distributorLevel = MemberLevel::query()
                ->where('member_level_code', 'DST')
                ->firstOrFail();
            $agentLevel = MemberLevel::query()
                ->where('member_level_code', 'AGT')
                ->firstOrFail();
            $resellerLevel = MemberLevel::query()
                ->where('member_level_code', 'RSL')
                ->firstOrFail();
            $agent = $this->member(
                $memberCodeService,
                'Ayu Agent Development',
                $agentLevel,
                $member->getKey(),
                '+6281200000101',
                $now->subDays(2),
            );
            $secondAgent = $this->member(
                $memberCodeService,
                'Bima Agent Development',
                $agentLevel,
                $member->getKey(),
                '+6281200000102',
                $now->subDays(4),
            );
            $reseller = $this->member(
                $memberCodeService,
                'Citra Reseller Development',
                $resellerLevel,
                $agent->getKey(),
                '+6281200000201',
                $now->subDay(),
            );
            $upgradedDistributor = $this->member(
                $memberCodeService,
                'Dewi Distributor Development',
                $distributorLevel,
                $member->getKey(),
                '+6281200000301',
                $now->subDays(10),
            );

            $this->memberAccount($agent);
            $this->memberAccount($secondAgent);
            $this->memberAccount($reseller);
            $this->memberAccount($upgradedDistributor);

            $this->memberAddress($member);
            $this->memberAddress($agent);
            $this->memberAddress($secondAgent);
            $this->memberAddress($reseller);
            $this->memberAddress($upgradedDistributor);
            $this->memberBank($member);
            $this->memberBank($agent);
            $this->memberBank($secondAgent);
            $this->memberBank($reseller);
            $this->memberBank($upgradedDistributor);
            $this->call(DevelopmentMasterDataSeeder::class);
            $spreadBank = BankCompany::query()
                ->where('bank_company_type', 'spread_payment')
                ->where('bank_company_bank_is_active', 1)
                ->orderBy('bank_company_id')
                ->firstOrFail();
            $customer = $this->customer($member, $now);
            $this->stocks($member, $products, $now);
            $this->saleTransaction($member, $customer, $now);
            $this->sharingProfit(
                $member,
                $upgradedDistributor,
                $spreadBank,
                $administrator,
                'DEV-SPREAD-PENDING',
                'pending',
                $now->subDays(3),
            );
            $this->sharingProfit(
                $member,
                $upgradedDistributor,
                $spreadBank,
                $administrator,
                'DEV-SPREAD-APPROVED',
                'approved',
                $now->subDays(2),
            );
            $this->sharingProfit(
                $member,
                $upgradedDistributor,
                $spreadBank,
                $administrator,
                'DEV-SPREAD-PAID',
                'paid',
                $now->subDay(),
            );

            $this->call([
                DevelopmentPartnershipDataSeeder::class,
                DevelopmentInventoryDataSeeder::class,
                DevelopmentCommerceDataSeeder::class,
                DevelopmentRewardDataSeeder::class,
            ]);

            BusinessConfig::put('development', [
                'frontend_enabled' => true,
                'seeded_at' => $now->toDateTimeString(),
            ], $now);
        });

        $this->command?->info(
            'Data development API berhasil disiapkan untuk member ID 1 dan administrator ID 1.',
        );
        $spreadPaymentDistributor = Member::query()
            ->where('member_mobilephone', '+6281200000301')
            ->firstOrFail();
        $this->command?->info(
            "Akun uji spread payment: {$spreadPaymentDistributor->member_code} "
            .'(Dewi Distributor Development). Password mengikuti DNY_INITIAL_MEMBER_PASSWORD.',
        );
    }

    private function assertCanRun(): void
    {
        $hasSeedMarker = BusinessConfig::get('development.seeded_at') !== null;
        $hasDevelopmentNetwork = Member::query()
            ->whereIn('member_mobilephone', [
                '+6281200000101',
                '+6281200000102',
                '+6281200000201',
                '+6281200000301',
            ])
            ->exists();

        if ($hasSeedMarker || $hasDevelopmentNetwork) {
            throw new LogicException(
                'Data development sudah tersedia dan tidak boleh di-seed ulang. '
                .'Jalankan migrate:fresh --seed, lalu db:seed --class=DevelopmentSeeder '
                .'pada database local/development untuk membuat ulang seluruh alur data.',
            );
        }
    }

    private function member(
        MemberCodeService $memberCodeService,
        string $name,
        MemberLevel $level,
        int $parentId,
        string $mobilePhone,
        CarbonImmutable $joinedAt,
    ): Member {
        $member = Member::query()->firstOrNew([
            'member_mobilephone' => $mobilePhone,
        ]);

        if (! $member->exists) {
            $sponsor = $parentId === 0 ? null : Member::query()->findOrFail($parentId);
            $member->member_code = $memberCodeService->next($level, $sponsor);
        }

        $member->fill([
            'member_member_level_id' => $level->getKey(),
            'member_parent_member_id' => $parentId,
            'member_name' => $name,
            'member_email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'member_status' => 1,
            'member_gender' => 'Perempuan',
            'member_join_datetime' => $joinedAt,
        ]);
        $member->save();

        return $member;
    }

    private function memberAddress(Member $member): void
    {
        if (MemberAddress::query()->where('member_address_member_id', $member->getKey())->exists()) {
            return;
        }

        $provinceId = (int) DB::table('ref_province')
            ->where('province_name', 'Jawa Timur')
            ->value('province_id');
        $cityId = (int) DB::table('ref_city')
            ->where('city_province_id', $provinceId)
            ->where('city_name', 'Surabaya')
            ->value('city_id');
        $districtId = (int) DB::table('ref_district')
            ->where('district_city_id', $cityId)
            ->where('district_name', 'Gubeng')
            ->value('district_id');
        $subdistrictId = (int) DB::table('ref_subdistrict')
            ->where('subdistrict_district_id', $districtId)
            ->where('subdistrict_name', 'Mojo')
            ->value('subdistrict_id');
        $countryId = (int) DB::table('ref_country')->orderBy('country_id')->value('country_id');

        if (in_array(0, [$provinceId, $cityId, $districtId, $subdistrictId], true)) {
            $provinceId = (int) DB::table('ref_province')->orderBy('province_id')->value('province_id');
            $cityId = (int) DB::table('ref_city')
                ->where('city_province_id', $provinceId)
                ->orderBy('city_id')
                ->value('city_id');
            $districtId = (int) DB::table('ref_district')
                ->where('district_city_id', $cityId)
                ->orderBy('district_id')
                ->value('district_id');
            $subdistrictId = (int) DB::table('ref_subdistrict')
                ->where('subdistrict_district_id', $districtId)
                ->orderBy('subdistrict_id')
                ->value('subdistrict_id');
        }

        if (in_array(0, [$provinceId, $cityId, $districtId, $subdistrictId], true)
            && DB::getDriverName() !== 'sqlite') {
            throw new LogicException('Referensi wilayah member development tidak lengkap.');
        }

        MemberAddress::query()->updateOrCreate(
            [
                'member_address_member_id' => $member->getKey(),
                'member_address_label' => 'Alamat Development',
            ],
            [
                'member_address_recipient' => $member->member_name,
                'member_address_phone' => $member->member_mobilephone,
                'member_address_full' => 'Jalan Mojo, Gubeng, Surabaya',
                'member_address_subdistrict_id' => $subdistrictId,
                'member_address_district_id' => $districtId,
                'member_address_city_id' => $cityId,
                'member_address_province_id' => $provinceId,
                'member_address_country_id' => $countryId,
                'member_address_is_default' => 1,
            ],
        );
    }

    private function memberAccount(Member $member): MemberAccount
    {
        $password = (string) config('initial_data.member.password');
        if (blank($password)) {
            throw new LogicException(
                'Akun member development tidak dibuat. Isi DNY_INITIAL_MEMBER_PASSWORD terlebih dahulu.'
            );
        }

        $groupId = match ((int) $member->member_member_level_id) {
            1 => 1,
            2 => 2,
            3 => 3,
            default => 1,
        };
        $account = MemberAccount::query()->firstOrNew([
            'member_account_member_id' => $member->getKey(),
        ]);
        $account->fill([
            'member_account_member_group_id' => $groupId,
            'member_account_username' => $member->member_code,
            'member_account_pin' => (string) config('initial_data.member.pin', '123456'),
        ]);
        if (! $account->exists) {
            $account->member_account_password = Hash::make($password);
        }
        $account->save();

        return $account;
    }

    private function memberBank(Member $member): MemberBankAccount
    {
        $bankId = (int) DB::table('ref_bank')->orderBy('bank_id')->value('bank_id');
        $accountNumber = '880000'.str_pad((string) $member->getKey(), 4, '0', STR_PAD_LEFT);

        return MemberBankAccount::query()->updateOrCreate(
            [
                'member_bank_account_member_id' => $member->getKey(),
                'member_bank_account_number' => $accountNumber,
            ],
            [
                'member_bank_account_bank_id' => $bankId,
                'member_bank_account_name' => $member->member_name,
                'member_bank_account_is_active' => 1,
                'member_bank_account_is_default' => 1,
            ],
        );
    }

    private function customer(Member $member, CarbonImmutable $now): Customer
    {
        return Customer::query()->updateOrCreate(
            ['customer_whatsapp' => '+6281300000001'],
            [
                'customer_member_id' => $member->getKey(),
                'customer_name' => 'Pelanggan Development',
                'customer_phone' => '+6281300000001',
                'customer_gender' => 'P',
                'customer_birth_date' => '1995-05-10',
                'customer_address' => 'Jalan Mojo, Gubeng, Surabaya',
                'customer_province_id' => $member->member_province_id,
                'customer_city_id' => $member->member_city_id,
                'customer_district_id' => $member->member_district_id,
                'customer_subdistrict_id' => $member->member_subdistrict_id,
                'customer_is_deleted' => 0,
                'customer_created_datetime' => $now->subHours(3),
            ],
        );
    }

    /** @param Collection<int, Product> $products */
    private function stocks(Member $member, $products, CarbonImmutable $now): void
    {
        foreach ($products->values() as $index => $product) {
            $balance = [24, 15, 8][$index] ?? 5;
            MemberStock::query()->updateOrCreate(
                [
                    'member_stock_member_id' => $member->getKey(),
                    'member_stock_product_id' => $product->getKey(),
                ],
                [
                    'member_stock_balance' => $balance,
                    'member_stock_transfer_in' => 0,
                    'member_stock_transfer_out' => 0,
                ],
            );

            $openingBalance = $balance + ($index === 0 ? 2 : 0);
            MemberStockLog::query()->updateOrCreate(
                [
                    'member_stock_log_member_id' => $member->getKey(),
                    'member_stock_log_product_id' => $product->getKey(),
                    'member_stock_log_note' => 'Saldo awal data development',
                ],
                [
                    'member_stock_log_type' => 'in',
                    'member_stock_log_quantity' => $openingBalance,
                    'member_stock_log_unit_price' => (int) $product->product_customer_price,
                    'member_stock_log_balance' => $openingBalance,
                    'member_stock_log_datetime' => $now->subDays(10),
                ],
            );
        }

        $product = $products->first();
        MemberStockLog::query()->updateOrCreate(
            [
                'member_stock_log_member_id' => $member->getKey(),
                'member_stock_log_product_id' => $product->getKey(),
                'member_stock_log_note' => 'Penyesuaian Stok: Barang rusak saat penyimpanan',
            ],
            [
                'member_stock_log_type' => 'out',
                'member_stock_log_quantity' => 2,
                'member_stock_log_unit_price' => (int) $product->product_customer_price,
                'member_stock_log_balance' => 24,
                'member_stock_log_datetime' => $now->subDays(3),
            ],
        );
    }

    private function saleTransaction(Member $member, Customer $customer, CarbonImmutable $now): void
    {
        $key = 'DEV-SALE-COMPLETED';
        $trx = $this->developmentTransaction($key) ?? new Trx;

        if (! $trx->exists) {
            $trx->trx_code = $this->transactionCodeService->next(
                'distributor',
                'customer',
                TransactionCodeService::deterministicUniqueSuffix($key),
            );
        }

        $trx->fill([
            'trx_parent_trx_id' => 0,
            'trx_is_preorder' => 0,
            'trx_seller_type' => 'distributor',
            'trx_seller_id' => $member->getKey(),
            'trx_buyer_type' => 'customer',
            'trx_buyer_id' => $customer->getKey(),
            'trx_type' => 'retail',
            'trx_reference_id' => 0,
            'trx_total_price' => 850_000,
            'trx_discount' => 0,
            'trx_discount_value' => 0,
            'trx_grand_total_price' => 850_000,
            'trx_shipping_cost' => 20_000,
            'trx_payment_charge' => 0,
            'trx_grand_total_nett_price' => 870_000,
            'trx_bill_remaining' => 0,
            'trx_bill_augment' => 0,
            'trx_bill_amount' => 870_000,
            'trx_payment_method' => 'transfer',
            'trx_shipping_method' => 'courier_manual',
            'trx_status' => 'completed',
            'trx_status_datetime' => $now->subHours(2),
            'trx_datetime' => $now->subHours(4),
        ]);
        $trx->save();
    }

    private function sharingProfit(
        Member $upline,
        Member $buyer,
        BankCompany $bankAccount,
        ?SiteAdministrator $administrator,
        string $trxCode,
        string $status,
        CarbonImmutable $datetime,
    ): void {
        $buyer->loadMissing('level');
        $buyerType = match ($buyer->level?->member_level_code) {
            'DST' => 'distributor',
            'AGT' => 'agent',
            'RSL' => 'reseller',
            default => throw new LogicException('Level pembeli data development tidak didukung.'),
        };
        $trx = $this->developmentTransaction($trxCode) ?? new Trx;

        if (! $trx->exists) {
            $trx->trx_code = $this->transactionCodeService->next(
                'warehouse',
                $buyerType,
                TransactionCodeService::deterministicUniqueSuffix($trxCode),
            );
        }

        $trx->fill([
            'trx_parent_trx_id' => 0,
            'trx_is_preorder' => 0,
            'trx_seller_type' => 'warehouse',
            'trx_seller_id' => 1,
            'trx_buyer_type' => $buyerType,
            'trx_buyer_id' => $buyer->getKey(),
            'trx_type' => 'stock',
            'trx_reference_id' => 0,
            'trx_total_price' => 1_000_000,
            'trx_discount' => 0,
            'trx_discount_value' => 0,
            'trx_grand_total_price' => 1_000_000,
            'trx_shipping_cost' => 0,
            'trx_payment_charge' => 0,
            'trx_grand_total_nett_price' => 1_000_000,
            'trx_bill_remaining' => 0,
            'trx_bill_augment' => 0,
            'trx_bill_amount' => 1_000_000,
            'trx_payment_method' => 'transfer',
            'trx_shipping_method' => 'pickup',
            'trx_status' => 'completed',
            'trx_status_datetime' => $datetime,
            'trx_datetime' => $datetime,
        ]);
        $trx->save();

        TrxSpreadPayment::query()->updateOrCreate(
            [
                'trx_spread_payment_trx_id' => $trx->getKey(),
                'trx_spread_payment_upline_id' => $upline->getKey(),
            ],
            [
                'trx_spread_payment_member_id' => $buyer->getKey(),
                'trx_spread_payment_bank_id' => $bankAccount->bank_company_bank_id,
                'trx_spread_payment_account_name' => $bankAccount->bank_company_bank_acc_name,
                'trx_spread_payment_account_number' => $bankAccount->bank_company_bank_acc_number,
                'trx_spread_payment_percentage' => 1,
                'trx_spread_payment_amount' => 10_000,
                'trx_spread_payment_status' => $status,
                'trx_spread_payment_approved_by' => $status === 'pending'
                    ? 0
                    : (int) ($administrator?->getKey() ?? 0),
                'trx_spread_payment_approved_datetime' => $status === 'pending'
                    ? null
                    : $datetime->addHour(),
                'trx_spread_payment_paid_by' => $status === 'paid'
                    ? (int) ($administrator?->getKey() ?? 0)
                    : 0,
                'trx_spread_payment_paid_datetime' => $status === 'paid'
                    ? $datetime->addHours(2)
                    : null,
                'trx_spread_payment_note' => "Data development {$status}",
                'trx_spread_payment_created_datetime' => $datetime,
            ],
        );
    }

    private function developmentTransaction(string $key): ?Trx
    {
        $suffixes = collect([$key, substr($key, 0, 20)])
            ->map(fn (string $value): string => TransactionCodeService::deterministicUniqueSuffix($value))
            ->unique();

        return Trx::query()->where(function ($query) use ($suffixes): void {
            foreach ($suffixes as $suffix) {
                $query->orWhere('trx_code', 'like', '%/'.$suffix);
            }
        })->first();
    }
}
