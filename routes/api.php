<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('/register', [RegisterController::class, 'register'])->name('register');
    Route::post('/login', [LoginController::class, 'login'])->name('login');
    Route::post('/forgot-password', [ResetPasswordController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('/reset-password', [ResetPasswordController::class, 'resetPassword'])->name('reset-password');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    });
});

Route::prefix('email')->name('verification.')->group(function (): void {
    Route::get('/verify/{id}/{hash}', [RegisterController::class, 'verify'])
        ->middleware('signed')
        ->name('verify');

    Route::post('/resend-verification', [RegisterController::class, 'resendVerification'])
        ->name('send');
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('categories', CategoryController::class)->names('categories');
    Route::apiResource('transactions', TransactionController::class)->names('transactions');
    Route::apiResource('documents', DocumentController::class)->names('documents');
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])
        ->name('documents.download');
});
