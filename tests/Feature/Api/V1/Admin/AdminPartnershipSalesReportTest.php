<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Customer;
use App\Models\Member;
use App\Models\MemberLevel;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use App\Models\Trx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPartnershipSalesReportTest extends TestCase
{
    use RefreshDatabase;

    private function createAdministrator(): SiteAdministrator
    {
        $group = SiteAdministratorGroup::query()->firstOrCreate(
            ['administrator_group_id' => 1],
            ['administrator_group_title' => 'Super Admin', 'administrator_group_is_active' => 1]
        );

        return SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->administrator_group_id,
            'administrator_username' => 'admin.test',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Admin Test',
            'administrator_email' => 'admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }

    private function createDistributor(): Member
    {
        MemberLevel::query()->firstOrCreate(
            ['member_level_id' => 1],
            ['member_level_code' => 'DST', 'member_level_name' => 'Distributor', 'member_level_is_active' => 1]
        );

        return Member::query()->create([
            'member_member_level_id' => 1,
            'member_code' => 'DST-001',
            'member_name' => 'Distributor 1',
            'member_email' => 'dst@example.com',
            'member_mobilephone' => '0812345',
            'member_status' => 1,
            'member_gender' => 'Perempuan',
            'member_join_datetime' => now(),
        ]);
    }

    private function createOrder(Member|Customer $buyer): Trx
    {
        $seller = Member::query()->firstOrCreate([
            'member_code' => 'DST-SELLER',
        ], [
            'member_member_level_id' => 1,
            'member_name' => 'Distributor Seller',
            'member_email' => 'seller@example.com',
            'member_mobilephone' => '0812346',
            'member_status' => 1,
            'member_gender' => 'Perempuan',
            'member_join_datetime' => now(),
        ]);

        return Trx::query()->create([
            'trx_code' => 'PO-'.str_pad((string) (Trx::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'trx_buyer_type' => $buyer instanceof Customer ? 'customer' : 'distributor',
            'trx_buyer_id' => $buyer->getKey(),
            'trx_seller_type' => 'distributor',
            'trx_seller_id' => $seller->member_id,
            'trx_type' => 'stock',
            'trx_is_preorder' => 0,
            'trx_total_price' => 100000,
            'trx_discount_value' => 0,
            'trx_grand_total_price' => 100000,
            'trx_shipping_cost' => 10000,
            'trx_payment_charge' => 0,
            'trx_grand_total_nett_price' => 110000,
            'trx_status' => 'completed',
            'trx_status_datetime' => now(),
            'trx_datetime' => now(),
        ]);
    }

    private function createCustomer(Member $owner): Customer
    {
        return Customer::query()->create([
            'customer_member_id' => $owner->member_id,
            'customer_name' => 'Customer 1',
            'customer_whatsapp' => '0812347',
            'customer_phone' => '0812347',
            'customer_is_deleted' => 0,
            'customer_created_datetime' => now(),
        ]);
    }

    public function test_administrator_can_list_partnership_sales_report(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $member = $this->createDistributor();
        $this->createOrder($member); // Creates a transaction where member is the buyer

        $this->getJson('/api/v1/admin/reports/partnership-sales?sort=-datetime')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'results' => [
                        '*' => [
                            'id',
                            'datetime',
                            'code',
                            'is_preorder',
                            'buyer' => [
                                'type',
                                'id',
                                'name',
                                'code',
                            ],
                            'seller' => [
                                'type',
                                'id',
                                'name',
                                'code',
                            ],
                            'total_price',
                            'status',
                        ],
                    ],
                    'pagination' => [
                        'total_data',
                        'total_page',
                        'total_display',
                        'first_page',
                        'last_page',
                        'prev',
                        'current',
                        'next',
                        'detail',
                        'start',
                        'end',
                    ],
                ],
            ])
            ->assertJsonPath('data.results.0.buyer.type', 'distributor')
            ->assertJsonPath('data.results.0.buyer.id', $member->member_id)
            ->assertJsonPath('data.results.0.buyer.name', 'Distributor 1')
            ->assertJsonPath('data.results.0.buyer.code', 'DST-001')
            ->assertJsonPath('data.results.0.seller.type', 'distributor')
            ->assertJsonPath('data.results.0.seller.name', 'Distributor Seller')
            ->assertJsonPath('data.results.0.seller.code', 'DST-SELLER');
    }

    public function test_administrator_can_view_partnership_sales_detail(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $member = $this->createDistributor();
        $trx = $this->createOrder($member);

        $this->getJson("/api/v1/admin/reports/partnership-sales/{$trx->trx_id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', $trx->trx_code)
            ->assertJsonPath('data.buyer.id', $member->member_id)
            ->assertJsonPath('data.buyer.name', 'Distributor 1')
            ->assertJsonPath('data.seller.type', 'distributor')
            ->assertJsonPath('data.seller.name', 'Distributor Seller');
    }

    public function test_administrator_can_search_partnership_sales_by_seller(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $member = $this->createDistributor();
        $this->createOrder($member);

        $this->getJson('/api/v1/admin/reports/partnership-sales?search=Distributor%20Seller')
            ->assertOk()
            ->assertJsonPath('data.pagination.total_data', 1)
            ->assertJsonPath('data.results.0.seller.name', 'Distributor Seller');
    }

    public function test_administrator_can_filter_partnership_sales_by_seller_and_buyer(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $member = $this->createDistributor();
        $this->createOrder($member);
        $this->createOrder($this->createCustomer($member));

        $this->getJson('/api/v1/admin/reports/partnership-sales?filter[seller_name][like]=%25Seller%25&filter[buyer_code][like]=%25DST-001%25&filter[seller_type]=distributor&filter[buyer_type]=distributor')
            ->assertOk()
            ->assertJsonPath('data.pagination.total_data', 1)
            ->assertJsonPath('data.results.0.buyer.code', 'DST-001')
            ->assertJsonPath('data.results.0.seller.name', 'Distributor Seller');
    }

    public function test_legacy_party_filter_format_remains_supported(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $member = $this->createDistributor();
        $this->createOrder($member);

        $this->getJson('/api/v1/admin/reports/partnership-sales?seller_name[like]=Seller&buyer_code[like]=DST-001')
            ->assertOk()
            ->assertJsonPath('data.pagination.total_data', 1);
    }

    public function test_customer_sales_are_included_with_customer_role(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $customer = $this->createCustomer($this->createDistributor());
        $this->createOrder($customer);

        $this->getJson('/api/v1/admin/reports/partnership-sales?filter[buyer_type]=customer')
            ->assertOk()
            ->assertJsonPath('data.pagination.total_data', 1)
            ->assertJsonPath('data.results.0.buyer.type', 'customer')
            ->assertJsonPath('data.results.0.buyer.id', $customer->customer_id)
            ->assertJsonPath('data.results.0.buyer.name', 'Customer 1')
            ->assertJsonPath('data.results.0.buyer.code', null);
    }

    public function test_company_sales_are_excluded_from_partnership_report(): void
    {
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $trx = $this->createOrder($this->createDistributor());
        $trx->update(['trx_seller_type' => 'warehouse']);

        $this->getJson('/api/v1/admin/reports/partnership-sales')
            ->assertOk()
            ->assertJsonPath('data.pagination.total_data', 0);

        $this->getJson("/api/v1/admin/reports/partnership-sales/{$trx->trx_id}")
            ->assertNotFound();
    }
}
