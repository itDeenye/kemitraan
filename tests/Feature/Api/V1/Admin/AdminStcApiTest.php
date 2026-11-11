<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminStcApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'services.stc.base_url' => 'https://stc.example.test',
            'services.stc.token' => 'stc-secret-token',
            'services.stc.connect_timeout' => 1,
            'services.stc.timeout' => 2,
        ]);

        $this->actingAs($this->createAdministrator(), 'admin_api');
        Http::preventStrayRequests();
    }

    public function test_administrator_can_load_stc_balance_and_mutations(): void
    {
        Http::fake([
            'stc.example.test/api/saldo/stc-user' => Http::response([
                'message' => 'Ok',
                'error' => false,
                'data' => [
                    'ewallet_client_id' => 6,
                    'ewallet_acc' => 200000,
                    'ewallet_paid' => 69821,
                    'ewallet_use_paid' => 69821,
                    'ewallet_wd_paid' => 0,
                    'ewallet_last_balance' => 130179,
                ],
            ]),
            'stc.example.test/api/saldo/list-mutasi*' => Http::response([
                'message' => 'Ok',
                'data' => [
                    'results' => [[
                        'ewallet_log_id' => '85',
                        'ewallet_log_client_id' => '6',
                        'ewallet_log_transaction_id' => '52',
                        'ewallet_log_category' => 'trx',
                        'ewallet_log_type' => 'out',
                        'ewallet_log_value' => 16400,
                        'ewallet_log_note' => 'Pengiriman Dengan Kode: DNY-001',
                        'ewallet_log_datetime' => '2026-05-11 16:36:17',
                    ]],
                    'pagination' => $this->pagination(),
                ],
            ]),
        ]);

        $this->getJson('/api/v1/admin/stc/balance')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.balance', 130179);

        $this->getJson('/api/v1/admin/stc/mutations?filter[type]=out&date_from=2026-05-01&date_to=2026-05-31&sort=-datetime')
            ->assertOk()
            ->assertJsonPath('data.results.0.id', 85)
            ->assertJsonPath('data.results.0.transaction_id', 52)
            ->assertJsonPath('data.results.0.type', 'out')
            ->assertJsonPath('data.results.0.amount', 16400)
            ->assertJsonPath('data.pagination.total_data', 1);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://stc.example.test/api/saldo/stc-user'
            && $request->hasHeader('Authorization', 'Bearer stc-secret-token'));
        Http::assertSent(fn (Request $request): bool => str_starts_with(
            $request->url(),
            'https://stc.example.test/api/saldo/list-mutasi?'
        ) && $request['filter'][0] === [
            'type' => 'string',
            'field' => 'ewallet_log_type',
            'comparison' => '=',
            'value' => 'out',
        ] && $request['filter'][1] === [
            'type' => 'date',
            'comparison' => 'bet',
            'field' => 'ewallet_log_datetime',
            'value' => '2026-05-01::2026-05-31',
        ]);
    }

    public function test_administrator_can_load_stc_top_up_history_and_options(): void
    {
        Http::fake([
            'stc.example.test/api/saldo/list-mutasi*' => Http::response([
                'message' => 'Ok',
                'data' => [
                    'results' => [[
                        'ewallet_log_id' => '36',
                        'ewallet_log_client_id' => '6',
                        'ewallet_log_transaction_id' => '0',
                        'ewallet_log_category' => 'topup',
                        'ewallet_log_type' => 'in',
                        'ewallet_log_value' => 500175,
                        'ewallet_log_note' => 'Bank BRI payment id PAY_123456',
                        'ewallet_log_datetime' => '2026-02-06 04:29:28',
                    ]],
                    'pagination' => $this->pagination(),
                ],
            ]),
            'stc.example.test/api/saldo/topup-va' => Http::response([
                'message' => 'Ok',
                'error' => false,
                'data' => [
                    'bank' => [[
                        'stc_topup_va_id' => '2',
                        'stc_topup_va_bank_id' => '129',
                        'stc_topup_va_bank_name' => 'Bank Rakyat Indonesia (BRI)',
                        'stc_topup_va_bank_code' => 'BRI',
                        'stc_topup_va_number' => '13281109044256706',
                        'stc_topup_va_is_active' => '1',
                    ]],
                    'code_topup_va' => '175',
                ],
            ]),
        ]);

        $this->getJson('/api/v1/admin/stc/top-ups')
            ->assertOk()
            ->assertJsonPath('data.results.0.bank_name', 'Bank BRI')
            ->assertJsonPath('data.results.0.amount', 500175)
            ->assertJsonPath('data.results.0.sender_name', null)
            ->assertJsonPath('data.results.0.status', 'completed')
            ->assertJsonPath('data.results.0.payment_reference', 'PAY_123456');

        $this->getJson('/api/v1/admin/stc/top-up-options')
            ->assertOk()
            ->assertJsonPath('data.top_up_code', '175')
            ->assertJsonPath('data.banks.0.bank_code', 'BRI')
            ->assertJsonPath('data.banks.0.virtual_account', '13281109044256706')
            ->assertJsonPath('data.banks.0.is_active', true);

        Http::assertSent(fn (Request $request): bool => str_starts_with(
            $request->url(),
            'https://stc.example.test/api/saldo/list-mutasi?'
        ) && $request['filter'][0] === [
            'type' => 'string',
            'field' => 'ewallet_log_category',
            'comparison' => '=',
            'value' => 'topup',
        ]);
    }

    #[DataProvider('stcSearchFields')]
    public function test_stc_search_is_forwarded_with_registered_provider_fields(string $endpoint, ?string $fields, string $expectedFields): void
    {
        Http::fake([
            'stc.example.test/api/saldo/list-mutasi*' => Http::response([
                'data' => ['results' => [], 'pagination' => $this->pagination()],
            ]),
        ]);
        $params = ['search' => '  PAY_123456  ', 'page' => 2, 'limit' => 5];
        if ($fields !== null) {
            $params['field_search'] = $fields;
        }

        $this->getJson('/api/v1/admin/stc/'.$endpoint.'?'.http_build_query($params))
            ->assertOk()
            ->assertJsonPath('data.results', [])
            ->assertJsonPath('data.pagination.total_data', 1);

        Http::assertSent(fn (Request $request): bool => ($request['search'] ?? null) === 'PAY_123456'
            && ($request['field_search'] ?? null) === $expectedFields
            && $request['page'] === 2
            && $request['limit'] === 5
            && ($endpoint !== 'top-ups' || $request['filter'][0]['value'] === 'topup'));
    }

    public static function stcSearchFields(): array
    {
        $defaultFields = 'ewallet_log_id,ewallet_log_transaction_id,ewallet_log_category,ewallet_log_type,ewallet_log_value,ewallet_log_note';

        return [
            'mutations default' => ['mutations', null, $defaultFields],
            'top-ups default' => ['top-ups', null, $defaultFields],
            'note' => ['mutations', 'note', 'ewallet_log_note'],
            'bank and payment reference share the note' => ['top-ups', 'bank_name,payment_reference', 'ewallet_log_note'],
            'unknown fields are not forwarded' => ['mutations', 'amount,unknown_column', 'ewallet_log_value'],
        ];
    }

    public function test_table_note_and_date_filters_are_translated_to_stc_v1_filters(): void
    {
        Http::fake(['stc.example.test/api/saldo/list-mutasi*' => Http::response([
            'data' => ['results' => [], 'pagination' => $this->pagination()],
        ])]);

        $this->getJson('/api/v1/admin/stc/mutations?'.http_build_query([
            'filter' => ['note' => ['like' => '%PAY_123%'], 'type' => 'out',
                'datetime' => ['gte' => '2026-11-01', 'lt' => '2026-12-01']],
        ]))->assertOk();

        Http::assertSentCount(1);
        $filters = Http::recorded()[0][0]['filter'];
        $this->assertContainsEquals(['type' => 'string', 'field' => 'ewallet_log_note', 'comparison' => '<>', 'value' => 'PAY_123'], $filters);
        $this->assertContainsEquals(['type' => 'date', 'field' => 'ewallet_log_datetime', 'comparison' => 'bet',
            'value' => '2026-11-01 00:00:00::2026-11-30 23:59:59'], $filters);
        $this->assertContainsEquals(['type' => 'string', 'field' => 'ewallet_log_type', 'comparison' => '=', 'value' => 'out'], $filters);
    }

    public function test_stc_provider_failure_returns_process_error(): void
    {
        Http::fake([
            'stc.example.test/*' => Http::response(['message' => 'Service unavailable'], 503),
        ]);

        $this->getJson('/api/v1/admin/stc/balance')
            ->assertStatus(502)
            ->assertJsonPath('error_code', 'process_error')
            ->assertJsonPath('message', 'Data layanan pengiriman belum dapat dimuat. Silakan coba kembali.');
    }

    #[DataProvider('invalidStcFilters')]
    public function test_invalid_stc_filters_are_rejected_without_contacting_provider(array $filter): void
    {
        $this->getJson('/api/v1/admin/stc/mutations?'.http_build_query(['filter' => $filter]))->assertUnprocessable();
        Http::assertNothingSent();
    }

    public static function invalidStcFilters(): array
    {
        return [
            'invalid date' => [['datetime' => ['gte' => 'not-a-date']]],
            'reversed range' => [['datetime' => ['gte' => '2026-12-01', 'lt' => '2026-11-01']]],
            'unsupported note operator' => [['note' => ['raw' => 'PAY_123']]],
            'multiple note operators' => [['note' => ['eq' => 'PAY_123', 'ne' => 'PAY_456']]],
        ];
    }

    #[DataProvider('singleStcFilters')]
    public function test_single_table_filters_use_the_equivalent_stc_v1_operator(array $filter, array $expected): void
    {
        Http::fake(['stc.example.test/api/saldo/list-mutasi*' => Http::response(['data' => ['results' => []]])]);
        $this->getJson('/api/v1/admin/stc/mutations?'.http_build_query(['filter' => $filter]))->assertOk();
        Http::assertSentCount(1);
        $this->assertContainsEquals($expected, Http::recorded()[0][0]['filter']);
    }

    public static function singleStcFilters(): array
    {
        return [
            'exact note' => [['note' => 'PAY_123'], ['type' => 'string', 'field' => 'ewallet_log_note', 'comparison' => '=', 'value' => 'PAY_123']],
            'excluded note' => [['note' => ['ne' => 'PAY_123']], ['type' => 'string', 'field' => 'ewallet_log_note', 'comparison' => '!=', 'value' => 'PAY_123']],
            'date lower bound' => [['datetime' => ['gte' => '2026-11-01']], ['type' => 'date', 'field' => 'ewallet_log_datetime', 'comparison' => '>=', 'value' => '2026-11-01']],
            'date exclusive upper bound' => [['datetime' => ['lt' => '2026-12-01']], ['type' => 'date', 'field' => 'ewallet_log_datetime', 'comparison' => '<', 'value' => '2026-12-01']],
        ];
    }

    /** @return array<string, int|bool|array<int, int>> */
    private function pagination(): array
    {
        return [
            'total_data' => 1,
            'total_page' => 1,
            'total_display' => 1,
            'first_page' => false,
            'last_page' => false,
            'prev' => 0,
            'current' => 1,
            'next' => 0,
            'detail' => [],
            'start' => 1,
            'end' => 1,
        ];
    }

    private function createAdministrator(): SiteAdministrator
    {
        $group = SiteAdministratorGroup::query()->create([
            'administrator_group_title' => 'Super Administrator',
            'administrator_group_type' => 'superuser',
            'administrator_group_is_active' => 1,
        ]);

        return SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->getKey(),
            'administrator_username' => 'stc.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'STC Admin',
            'administrator_email' => 'stc.admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }
}
