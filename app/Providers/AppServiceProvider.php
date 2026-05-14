<?php

namespace App\Providers;

use App\Interfaces\CategoryServiceInterface;
use App\Models\User;
use App\Services\CategoryService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CategoryServiceInterface::class, CategoryService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            return url('/api/auth/reset-password?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset()));
        });

        Gate::define('manage-categories', fn (User $user): bool => $user->isAdmin());
    }
}
