<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiJsonEncodingTest extends TestCase
{
    public function test_api_json_urls_are_returned_without_escaped_slashes(): void
    {
        $mediaUrl = '/api/v1/member/media/uploads/a36387b6-d84d-4bf2-a939-dbea95dd9452/content';

        Route::middleware('api')->get('/api/v1/testing/json-url', fn () => response()->json([
            'data' => ['image_url' => $mediaUrl],
        ]));

        $response = $this->getJson('/api/v1/testing/json-url')
            ->assertOk()
            ->assertJsonPath('data.image_url', $mediaUrl);

        $this->assertStringContainsString($mediaUrl, $response->getContent());
        $this->assertStringNotContainsString('\/api\/v1', $response->getContent());
    }
}
