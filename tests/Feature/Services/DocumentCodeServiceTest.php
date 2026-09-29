<?php

namespace Tests\Feature\Services;

use App\Services\Document\DocumentCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_an_independent_sequence_for_each_document_prefix(): void
    {
        $service = app(DocumentCodeService::class);

        $firstReturn = $service->next(DocumentCodeService::RETURN, 'A7K9Q2');
        $this->assertSame('RTR/000001/A7K9Q2', $firstReturn);
        DB::table('return')->insert([
            'return_code' => $firstReturn,
            'return_goods_receive_id' => 0,
            'return_member_id' => 0,
            'return_created_datetime' => now(),
        ]);
        $this->assertSame(
            'RTR/000002/B8L0R3',
            $service->next(DocumentCodeService::RETURN, 'B8L0R3'),
        );
        $this->assertSame('GRN/000001/C9M1S4', $service->next(DocumentCodeService::GOODS_RECEIVE, 'C9M1S4'));
        $this->assertDatabaseMissing('config', ['config_key' => 'document_code.rtr_sequence']);
    }

    public function test_it_rejects_unsupported_prefixes_and_invalid_suffixes(): void
    {
        $service = app(DocumentCodeService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->next('INV', 'ABC123');
    }
}
