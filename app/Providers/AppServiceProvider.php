<?php

namespace App\Providers;

use App\Interfaces\CategoryServiceInterface;
use App\Interfaces\TransactionServiceInterface;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CategoryService;
use App\Services\TransactionService;
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
        $this->app->bind(TransactionServiceInterface::class, TransactionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            return url('/api/auth/reset-password?token=' . $token . '&email=' . urlencode($notifiable->getEmailForPasswordReset()));
        });

        Gate::define('manage-categories', fn(User $user): bool => $user->isAdmin());

        Gate::define('create-transaction', fn(User $user): bool => $user->isClient());

        Gate::define(
            'view-transaction',
            fn(User $user, Transaction $transaction): bool => $user->isAdmin() || $transaction->user_id === $user->id
        );

        Gate::define(
            'modify-transaction',
            fn(User $user, Transaction $transaction): bool => $transaction->user_id === $user->id
        );
    }
}
