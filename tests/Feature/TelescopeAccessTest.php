<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateTelescope;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use App\Providers\TelescopeServiceProvider;
use App\Support\TelescopeRequestTags;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\Watchers\CommandWatcher;
use Laravel\Telescope\Watchers\JobWatcher;
use Laravel\Telescope\Watchers\RequestWatcher;
use Laravel\Telescope\Watchers\ScheduleWatcher;
use Tests\TestCase;

class TelescopeAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', AuthenticateTelescope::class])
            ->get('/_test/telescope-protected', fn () => 'Telescope protected');
    }

    public function test_telescope_requires_an_administrator_session(): void
    {
        $this->get('/_test/telescope-protected')
            ->assertRedirect(route('telescope.login'));

        $this->get('/telescope-login')
            ->assertOk()
            ->assertSee('DNY Telescope')
            ->assertSee('favicon.ico')
            ->assertSee('Masuk menggunakan akun administrator DNY yang aktif.');
    }

    public function test_telescope_dashboard_uses_dny_branding(): void
    {
        config()->set('telescope.enabled', true);
        $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class, true);

        $html = view('telescope::layout')->render();

        $this->assertStringContainsString('<title>DNY Telescope</title>', $html);
        $this->assertStringContainsString('class="dny-telescope-logo"', $html);
        $this->assertStringContainsString('<strong>DNY</strong> Telescope', $html);
    }

    public function test_active_administrator_can_login_to_and_logout_from_telescope(): void
    {
        $administrator = $this->createAdministrator();

        $this->post('/telescope-login', [
            'username' => 'telescope.admin',
            'password' => 'Secret123',
        ])->assertRedirect('/telescope');

        $this->assertAuthenticatedAs($administrator, 'telescope');
        $this->get('/_test/telescope-protected')
            ->assertOk()
            ->assertSee('Telescope protected');

        $this->post('/telescope-logout')
            ->assertRedirect(route('telescope.login'));
        $this->assertGuest('telescope');
    }

    public function test_invalid_telescope_credentials_use_an_indonesian_validation_message(): void
    {
        $this->createAdministrator();

        $this->from('/telescope-login')->post('/telescope-login', [
            'username' => 'telescope.admin',
            'password' => 'PasswordSalah',
        ])->assertRedirect('/telescope-login')
            ->assertSessionHasErrors([
                'username' => 'Username atau kata sandi administrator tidak sesuai.',
            ]);

        $this->assertGuest('telescope');
    }

    public function test_command_job_and_schedule_watchers_are_enabled(): void
    {
        $this->assertTrue(config('telescope.watchers.'.CommandWatcher::class.'.enabled'));
        $this->assertTrue(config('telescope.watchers.'.JobWatcher::class));
        $this->assertSame([], config('telescope.watchers.'.RequestWatcher::class.'.ignore_http_methods'));
        $this->assertTrue(config('telescope.watchers.'.ScheduleWatcher::class));
    }

    public function test_request_entries_receive_searchable_date_and_endpoint_tags(): void
    {
        $this->app->register(TelescopeServiceProvider::class);
        config()->set('app.timezone', 'Asia/Jakarta');

        $entry = IncomingEntry::make([
            'uri' => '/api/v1/admin/products/10?include=stock',
        ])->type(EntryType::REQUEST);
        $entry->recordedAt = CarbonImmutable::parse('2026-09-02 17:30:00', 'UTC');

        $expectedTags = [
            'date:2026-09-03',
            'endpoint:/api/v1/admin/products',
            'endpoint:/api/v1/admin/products/10',
            'date-endpoint:2026-09-03:/api/v1/admin/products',
        ];
        $generatedTags = app(TelescopeRequestTags::class)->for($entry);

        foreach ($expectedTags as $expectedTag) {
            $this->assertContains($expectedTag, $generatedTags);
        }
        $this->assertNotContains('endpoint:/api/v1/admin/products/10?include=stock', $generatedTags);

        $registeredTags = collect(Telescope::$tagUsing)
            ->flatMap(fn (callable $tagger): array => $tagger($entry))
            ->all();

        foreach ($expectedTags as $expectedTag) {
            $this->assertContains($expectedTag, $registeredTags);
        }
    }

    public function test_non_request_entries_do_not_receive_request_tags(): void
    {
        $entry = IncomingEntry::make([])->type(EntryType::LOG);

        $this->assertSame([], app(TelescopeRequestTags::class)->for($entry));
    }

    private function createAdministrator(): SiteAdministrator
    {
        $group = SiteAdministratorGroup::query()->create([
            'administrator_group_title' => 'Telescope Administrator',
            'administrator_group_type' => 'superuser',
            'administrator_group_is_active' => 1,
        ]);

        return SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->administrator_group_id,
            'administrator_username' => 'telescope.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Telescope Admin',
            'administrator_email' => 'telescope.admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }
}
