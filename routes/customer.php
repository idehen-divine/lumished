<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StoreController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    /**********************    Auth Routes    ***********************/
    Route::prefix('auth')->name('auth.')->controller(CustomerAuthController::class)->group(function () {
        Route::post('/register', 'register')->middleware('throttle:auth')->name('register');
        Route::post('/sessions', 'login')->middleware('throttle:auth')->name('login');
        Route::post('/sessions/2fa', 'verifyTwoFactor')->middleware('throttle:auth')->name('2fa.verify');
        Route::post('/sessions/2fa/email', 'resendTwoFactorEmail')->middleware('throttle:auth')->name('2fa.email');
        Route::delete('/sessions', 'logout')->middleware(['auth:sanctum', 'customer'])->name('logout');
        Route::post('/password/reset/request', 'forgotPassword')->middleware('throttle:auth')->name('password.forgot');
        Route::post('/password/reset/verification', 'verifyPasswordOtp')->middleware('throttle:auth')->name('password.verify-otp');
        Route::post('/password/reset/confirmation', 'resetPassword')->middleware('throttle:auth')->name('password.reset');
    });

    /**********************    Protected Routes    ***********************/
    Route::middleware(['auth:sanctum', 'customer', 'verified'])->group(function () {

        /**********************    Store Routes    ***********************/
        Route::prefix('stores')->name('stores.')->controller(StoreController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/{id}', 'show')->name('show');
            Route::put('/{id}', 'update')->name('update');
            Route::delete('/{id}', 'destroy')->name('destroy');
        });

        Route::prefix('store/{storeSlug}')->name('stores.')->group(function () {
            Route::prefix('products')->name('products.')->controller(ProductController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('/{id}', 'show')->name('show');
                Route::put('/{id}', 'update')->name('update');
                Route::delete('/{id}', 'destroy')->name('destroy');
            });

            Route::prefix('categories')->name('categories.')->controller(CategoryController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('/{id}', 'show')->name('show');
                Route::put('/{id}', 'update')->name('update');
                Route::delete('/{id}', 'destroy')->name('destroy');
            });
        });

        /**********************    Account Routes    ***********************/
        Route::prefix('account')->name('account.')->group(function () {

            /**********************    Security Routes    ***********************/
            Route::prefix('security')->name('security.')->controller(CustomerAuthController::class)->group(function () {
                Route::patch('/email', 'updateEmail')->name('email.update')->withoutMiddleware('verified');
                Route::post('/email/verification', 'verifyEmail')->name('email.verify')->withoutMiddleware('verified');
                Route::post('/email/verification/resend', 'resendVerificationEmail')->name('email.resend')->withoutMiddleware('verified');
                Route::post('/2fa/setup', 'setupTwoFactor')->name('2fa.setup');
                Route::post('/2fa/confirm', 'confirmTwoFactor')->name('2fa.confirm');
                Route::delete('/2fa', 'disableTwoFactor')->name('2fa.disable');
                Route::patch('/password', 'initiatePasswordChange')->name('password.update');
                Route::post('/password/verification', 'verifyPasswordChangeOtp')->name('password.update.verify');
                Route::post('/password/confirmation', 'confirmPasswordUpdate')->name('password.update.confirm');
                Route::delete('/account', 'deleteAccount')->name('account.delete');
                Route::get('/account/export', 'exportData')->name('account.export');
            });
        });
    });

});
