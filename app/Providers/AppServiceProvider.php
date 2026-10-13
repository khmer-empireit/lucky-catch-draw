<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The app has no standalone "login" route — every user authenticates
        // through the Filament admin panel's own login page.
        Authenticate::redirectUsing(fn () => route('filament.admin.auth.login'));
    }
}
