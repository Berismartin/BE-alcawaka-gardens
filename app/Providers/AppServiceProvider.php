<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
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
        // Reset emails open the Nuxt portal, not a Laravel page.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => rtrim(config('app.frontend_url'), '/')
            .'/portal/reset-password?'.http_build_query(['email' => $user->email, 'token' => $token]));
    }
}
