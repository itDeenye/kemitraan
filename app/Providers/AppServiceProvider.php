<?php

namespace App\Providers;

use App\Contracts\Integrations\StcGateway;
use App\Integrations\Stc\HttpStcGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(StcGateway::class, HttpStcGateway::class);

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('dny-login', function (Request $request): Limit {
            $username = Str::lower((string) $request->input('username'));

            return Limit::perMinute(config('dny_auth.login_rate_limit_per_minute'))
                ->by($username.'|'.$request->ip());
        });

        RateLimiter::for('dny-password-reset', function (Request $request): Limit {
            $email = Str::lower(trim((string) $request->input('email')));

            return Limit::perMinute(config('dny_auth.password_reset_rate_limit_per_minute'))
                ->by($email.'|'.$request->ip());
        });

        RateLimiter::for('dny-api', function (Request $request): Limit {
            if ($this->app->environment(['local', 'testing'])) {
                return Limit::none();
            }

            $userId = $request->user()?->getAuthIdentifier();
            $token = $request->bearerToken();
            $key = match (true) {
                filled($userId) => 'user:'.$userId,
                filled($token) => 'token:'.hash('sha256', $token),
                default => 'ip:'.$request->ip(),
            };

            return Limit::perMinute(config('dny_auth.api_rate_limit_per_minute'))
                ->by($key);
        });
    }
}
