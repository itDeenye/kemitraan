<?php

namespace Tests\Feature\Services;

use App\Services\Transaction\TransactionCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_one_global_sequence_for_all_transaction_directions(): void
    {
        $service = app(TransactionCodeService::class);

        $companyToDistributor = $service->next('warehouse', 'distributor', 'ABC123');

        $this->assertSame('TRX/CMP/DST/000001/ABC123', $companyToDistributor);
        DB::table('trx')->insert([
            'trx_code' => $companyToDistributor,
            'trx_seller_type' => 'warehouse',
            'trx_seller_id' => 1,
            'trx_buyer_type' => 'distributor',
            'trx_buyer_id' => 1,
            'trx_type' => 'stock',
            'trx_total_price' => 0,
            'trx_status_datetime' => now(),
            'trx_datetime' => now(),
        ]);
        $distributorToAgent = $service->next('distributor', 'agent', 'XYZ789');
        $this->assertSame('TRX/DST/AGT/000002/XYZ789', $distributorToAgent);
        $this->assertDatabaseMissing('config', ['config_key' => 'transaction.code_sequence']);
    }

    public function test_it_supports_all_business_parties_and_random_unique_suffix(): void
    {
        $service = app(TransactionCodeService::class);
        $code = $service->next('agent', 'reseller');

        $this->assertMatchesRegularExpression(
            '~^TRX/AGT/RSL/000001/[A-Z0-9]{6}$~',
            $code,
        );
        DB::table('trx')->insert([
            'trx_code' => $code,
            'trx_seller_type' => 'agent',
            'trx_seller_id' => 1,
            'trx_buyer_type' => 'reseller',
            'trx_buyer_id' => 1,
            'trx_type' => 'stock',
            'trx_total_price' => 0,
            'trx_status_datetime' => now(),
            'trx_datetime' => now(),
        ]);

        $customerCode = $service->next('reseller', 'customer');
        $this->assertMatchesRegularExpression(
            '~^TRX/RSL/CUS/000002/[A-Z0-9]{6}$~',
            $customerCode,
        );
    }

    public function test_it_keeps_the_root_sequence_for_transactions_in_the_same_preorder_chain(): void
    {
        $service = app(TransactionCodeService::class);
        $rootCode = $service->next('agent', 'reseller', 'ABC123');

        DB::table('trx')->insert([
            'trx_code' => $rootCode,
            'trx_seller_type' => 'agent',
            'trx_seller_id' => 2,
            'trx_buyer_type' => 'reseller',
            'trx_buyer_id' => 3,
            'trx_type' => 'stock',
            'trx_total_price' => 0,
            'trx_status_datetime' => now(),
            'trx_datetime' => now(),
        ]);

        $agentCode = $service->nextInChain($rootCode, 'distributor', 'agent', 'DEF456');
        $companyCode = $service->nextInChain($agentCode, 'warehouse', 'distributor', 'GHI789');

        $this->assertSame('TRX/AGT/RSL/000001/ABC123', $rootCode);
        $this->assertSame('TRX/DST/AGT/000001/DEF456', $agentCode);
        $this->assertSame('TRX/CMP/DST/000001/GHI789', $companyCode);
    }
}
