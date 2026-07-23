<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AdminStoreController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    /**********************    Auth Routes    ***********************/
    Route::prefix('auth')->name('auth.')->controller(AdminAuthController::class)->group(function () {
        Route::post('/sessions', 'login')->middleware('throttle:auth')->name('login');
        Route::post('/sessions/2fa', 'verifyTwoFactor')->middleware('throttle:auth')->name('2fa.verify');
        Route::post('/sessions/2fa/email', 'resendTwoFactorEmail')->middleware('throttle:auth')->name('2fa.email');
        Route::delete('/sessions', 'logout')->middleware(['auth:sanctum', 'admin'])->name('logout');
        Route::post('/password/forgot', 'forgotPassword')->middleware('throttle:auth')->name('password.forgot');
        Route::post('/password/verify-otp', 'verifyPasswordOtp')->middleware('throttle:auth')->name('password.verify-otp');
        Route::post('/password/reset', 'resetPassword')->middleware('throttle:auth')->name('password.reset');
    });

    /**********************    Protected Routes    ***********************/
    Route::middleware(['auth:sanctum', 'admin', 'verified'])->group(function () {

        /**********************    Admin Store Routes    ***********************/
        Route::prefix('stores')->name('stores.')->controller(AdminStoreController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{id}', 'show')->name('show');
            Route::put('/{id}/status', 'updateStatus')->name('status.update');
        });

        Route::prefix('store/{storeSlug}')->name('stores.')->controller(AdminStoreController::class)->group(function () {
            Route::get('/products', 'products')->name('products');
        });

        /**********************    Account Routes    ***********************/
        Route::prefix('account')->name('account.')->group(function () {

            /**********************    Security Routes    ***********************/
            Route::prefix('security')->name('security.')->controller(AdminAuthController::class)->group(function () {
                Route::patch('/email/update', 'updateEmail')->name('email.update')->withoutMiddleware('verified');
                Route::post('/email/verify', 'verifyEmail')->name('email.verify')->withoutMiddleware('verified');
                Route::post('/email/resend', 'resendVerificationEmail')->name('email.resend')->withoutMiddleware('verified');
                Route::post('/2fa/setup', 'setupTwoFactor')->name('2fa.setup');
                Route::post('/2fa/confirm', 'confirmTwoFactor')->name('2fa.confirm');
                Route::delete('/2fa', 'disableTwoFactor')->name('2fa.disable');
                Route::patch('/password/update', 'initiatePasswordChange')->name('password.update');
                Route::post('/password/update/verify', 'verifyPasswordChangeOtp')->name('password.update.verify');
                Route::post('/password/update/confirm', 'confirmPasswordUpdate')->name('password.update.confirm');
                Route::delete('/account', 'deleteAccount')->name('account.delete');
                Route::get('/account/export', 'exportData')->name('account.export');
            });
        });
    });

});
