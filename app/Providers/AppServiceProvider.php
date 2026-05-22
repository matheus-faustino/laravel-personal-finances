<?php

namespace App\Providers;

use App\Interfaces\CategoryServiceInterface;
use App\Interfaces\DocumentServiceInterface;
use App\Interfaces\TransactionServiceInterface;
use App\Models\Document;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CategoryService;
use App\Services\DocumentService;
use App\Services\TransactionService;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
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
        $this->app->bind(DocumentServiceInterface::class, DocumentService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            return url('/api/auth/reset-password?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset()));
        });

        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(SecurityScheme::http('bearer'));
        });

        Gate::define('manage-categories', fn (User $user): bool => $user->isAdmin());

        Gate::define('create-transaction', fn (User $user): bool => $user->isClient());

        Gate::define(
            'view-transaction',
            fn (User $user, Transaction $transaction): bool => $user->isAdmin() || $transaction->user_id === $user->id
        );

        Gate::define(
            'modify-transaction',
            fn (User $user, Transaction $transaction): bool => $transaction->user_id === $user->id
        );

        Gate::define('create-document', fn (User $user): bool => $user->isClient());

        Gate::define(
            'view-document',
            fn (User $user, Document $document): bool => $user->isAdmin() || $document->user_id === $user->id
        );

        Gate::define(
            'modify-document',
            fn (User $user, Document $document): bool => $document->user_id === $user->id
        );
    }
}
