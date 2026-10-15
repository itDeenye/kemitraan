<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class VersionTest extends TestCase
{
    public function test_release_version_is_public_and_matches_the_committed_configuration(): void
    {
        $configuration = require config_path('api.php');

        $this->assertMatchesRegularExpression('/^v\d+\.\d+\.\d+$/', $configuration['version']['backend']);
        $this->assertMatchesRegularExpression('/^v\d+\.\d+\.\d+$/', $configuration['version']['frontend']);
        foreach ($configuration['released_at'] as $releasedAt) {
            $this->assertNotEmpty($releasedAt);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+07:00$/', $releasedAt);
        }

        $this->getJson('/api/v1/version')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Versi aplikasi berhasil diambil.',
                'data' => [
                    ...$configuration['version'],
                    'released_at' => [
                        'backend' => substr($configuration['released_at']['backend'], 0, 10),
                        'frontend' => substr($configuration['released_at']['frontend'], 0, 10),
                    ],
                    'released_at_datetime' => [
                        'backend' => $configuration['released_at']['backend'],
                        'frontend' => $configuration['released_at']['frontend'],
                    ],
                ],
            ]);

        $this->withoutVite()
            ->get('/version')
            ->assertOk()
            ->assertViewIs('version');
    }

    public function test_component_versions_are_returned_as_strings(): void
    {
        config([
            'api.version.backend' => 'v1.2.0',
            'api.version.frontend' => 'v1.1.3',
        ]);

        $this->getJson('/api/v1/version')
            ->assertOk()
            ->assertJsonPath('data.backend', 'v1.2.0')
            ->assertJsonPath('data.frontend', 'v1.1.3');
    }

    public function test_missing_component_versions_fall_back_to_v1_0_0(): void
    {
        config([
            'api.version.backend' => null,
            'api.version.frontend' => '',
        ]);

        $this->getJson('/api/v1/version')
            ->assertOk()
            ->assertJsonPath('data.backend', 'v1.0.0')
            ->assertJsonPath('data.frontend', 'v1.0.0');
    }

    public function test_version_does_not_change_with_the_clock_or_request_parameters(): void
    {
        config([
            'api.version.backend' => 'v1.2.1',
            'api.version.frontend' => 'v1.1.3',
        ]);
        $this->travelTo(now()->addDay());

        $this->getJson('/api/v1/version?backend=v9.0.0')
            ->assertOk()
            ->assertJsonPath('data.backend', 'v1.2.1');
    }

    public function test_component_release_dates_are_returned_from_configuration(): void
    {
        config([
            'api.released_at.backend' => '2026-10-13',
            'api.released_at.frontend' => '2026-10-12',
        ]);

        $this->getJson('/api/v1/version')
            ->assertOk()
            ->assertJsonPath('data.released_at.backend', '2026-10-13')
            ->assertJsonPath('data.released_at.frontend', '2026-10-12')
            ->assertJsonPath('data.released_at_datetime', ['backend' => null, 'frontend' => null]);
    }

    public function test_release_timestamps_preserve_date_only_fields_for_older_frontends(): void
    {
        config([
            'api.released_at.backend' => '2026-10-15T09:37:51+07:00',
            'api.released_at.frontend' => '2026-10-14T18:13:27Z',
        ]);

        $this->travelTo('2027-01-01 23:59:00');

        $this->getJson('/api/v1/version?released_at_datetime[backend]=2099-01-01T00:00:00Z')
            ->assertOk()
            ->assertJsonPath('data.released_at', [
                'backend' => '2026-10-15',
                'frontend' => '2026-10-15',
            ])
            ->assertJsonPath('data.released_at_datetime', [
                'backend' => '2026-10-15T09:37:51+07:00',
                'frontend' => '2026-10-15T01:13:27+07:00',
            ]);
    }

    public function test_invalid_release_dates_remain_unknown_instead_of_breaking_the_endpoint(): void
    {
        foreach (['invalid-date', 'tomorrow', '2026-02-30', '2026-10-15T25:37:51+07:00'] as $invalidDate) {
            config([
                'api.released_at.backend' => $invalidDate,
                'api.released_at.frontend' => $invalidDate,
            ]);

            $this->getJson('/api/v1/version')
                ->assertOk()
                ->assertJsonPath('data.released_at', ['backend' => null, 'frontend' => null])
                ->assertJsonPath('data.released_at_datetime', ['backend' => null, 'frontend' => null]);
        }
    }

    public function test_unknown_component_release_dates_remain_null(): void
    {
        config([
            'api.released_at.backend' => null,
            'api.released_at.frontend' => '',
        ]);

        $this->getJson('/api/v1/version')
            ->assertOk()
            ->assertJsonPath('data.released_at', [
                'backend' => null,
                'frontend' => null,
            ])
            ->assertJsonPath('data.released_at_datetime', ['backend' => null, 'frontend' => null]);
    }

    public function test_release_dates_do_not_change_with_the_clock_or_request_parameters(): void
    {
        config([
            'api.released_at.backend' => '2026-10-13',
            'api.released_at.frontend' => '2026-10-12',
        ]);

        foreach (['2026-10-14 00:01:00', '2027-01-01 23:59:00'] as $clock) {
            $this->travelTo($clock);

            $this->getJson('/api/v1/version?released_at[backend]=2099-01-01')
                ->assertOk()
                ->assertJsonPath('data.released_at', [
                    'backend' => '2026-10-13',
                    'frontend' => '2026-10-12',
                ]);
        }
    }

    public function test_unknown_release_dates_do_not_use_the_current_date(): void
    {
        config(['api.released_at' => []]);
        $this->travelTo('2027-01-01 12:00:00');

        $this->getJson('/api/v1/version')
            ->assertOk()
            ->assertJsonPath('data.released_at', [
                'backend' => null,
                'frontend' => null,
            ]);
    }

    public function test_version_response_is_not_cached(): void
    {
        $response = $this->getJson('/api/v1/version')->assertOk();

        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertSame('0', $response->headers->getCacheControlDirective('max-age'));
    }

    public function test_version_endpoint_is_read_only(): void
    {
        $this->postJson('/api/v1/version', ['backend' => 'v9.0.0'])
            ->assertStatus(405)
            ->assertJsonPath('error_code', 'process_error');

        $this->postJson('/version', ['frontend' => 'v9.0.0'])
            ->assertStatus(405);
    }

    public function test_version_route_uses_the_existing_v1_prefix(): void
    {
        $this->assertSame('/api/v1/version', route('api.v1.version', absolute: false));
        $this->assertSame('/version', route('version', absolute: false));
    }
}
