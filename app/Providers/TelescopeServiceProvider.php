<?php

namespace App\Providers;

use App\Models\SiteAdministrator;
use App\Support\TelescopeEntryFilter;
use App\Support\TelescopeRequestTags;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        Telescope::filter(fn (IncomingEntry $entry): bool => true);
        Telescope::filterBatch(
            fn (Collection $entries): bool => app(TelescopeEntryFilter::class)->shouldRecordBatch($entries)
        );
        Telescope::tag(
            fn (IncomingEntry $entry): array => app(TelescopeRequestTags::class)->for($entry)
        );
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters([
            '_token',
            'password',
            'password_confirmation',
            'pin',
            'api_token',
            'access_token',
            'refresh_token',
            'callback_token',
            'client_secret',
            'secret',
            'token',
        ]);

        Telescope::hideRequestHeaders([
            'authorization',
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'x-supply-chain-callback-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define(
            'viewTelescope',
            function ($user = null): bool {
                $administrator = Auth::guard('telescope')->user();

                return $administrator instanceof SiteAdministrator
                    && (bool) $administrator->administrator_is_active;
            },
        );
    }
}
