<?php

namespace App\Providers;

use App\Http\Responses\RoleBasedLoginResponse;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\LoginResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponse::class, RoleBasedLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthRedirects();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Force HTTPS scheme when the app URL is https (e.g. behind ngrok or a reverse proxy).
        // This ensures asset URLs use https:// and avoids mixed-content browser blocks.
        if (str_starts_with(config('app.url', ''), 'https')) {
            URL::forceScheme('https');
        }

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureAuthRedirects(): void
    {
        RedirectIfAuthenticated::redirectUsing(function () {
            if (auth()->guest()) {
                return null;
            }

            $role = auth()->user()?->getRoleNames()->first();

            return match ($role) {
                'system-admin' => '/admin',
                'secretariat' => '/secretariat',
                'hrdc' => '/hrdc',
                'supervisor' => '/supervisor',
                default => '/employee',
            };
        });
    }
}
