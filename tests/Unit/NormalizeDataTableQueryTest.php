<?php

namespace Tests\Unit;

use App\Http\Middleware\NormalizeDataTableQuery;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class NormalizeDataTableQueryTest extends TestCase
{
    public function test_nested_resource_fields_are_normalized_for_all_data_table_parameters(): void
    {
        $request = Request::create('/api/v1/test', 'GET', [
            'filter' => [
                'buyer.type' => 'distributor',
                'buyer.id' => ['gte' => 10, 'lt' => 20],
                'buyer.name' => ['like' => '%Budi%'],
                'status' => 'processing',
            ],
            'field_search' => 'buyer.code, buyer.name',
            'sort' => '-buyer.name,ordered_at',
        ]);

        (new NormalizeDataTableQuery)->handle($request, fn (): Response => new Response);

        $this->assertSame([
            'buyer_type' => 'distributor',
            'buyer_id' => ['gte' => 10, 'lt' => 20],
            'buyer_name' => ['like' => '%Budi%'],
            'status' => 'processing',
        ], $request->query('filter'));
        $this->assertSame('buyer_code,buyer_name', $request->query('field_search'));
        $this->assertSame('-buyer_name,ordered_at', $request->query('sort'));
    }

    public function test_canonical_filter_field_wins_when_both_formats_are_sent(): void
    {
        $request = Request::create('/api/v1/test', 'GET', [
            'filter' => [
                'buyer.type' => 'agent',
                'buyer_type' => 'distributor',
            ],
        ]);

        (new NormalizeDataTableQuery)->handle($request, fn (): Response => new Response);

        $this->assertSame(['buyer_type' => 'distributor'], $request->query('filter'));
    }
}
