<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $frontend = rtrim(env('FRONTEND_URL', 'http://localhost:3001'), '/');

            return $frontend.'/?reset_token='.urlencode($token).'&email='.urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}
