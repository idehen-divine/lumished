<?php

use App\Http\Controllers\Api\StoreController;
use Illuminate\Support\Facades\Route;

/**********************    Public Store Routes    ***********************/
Route::prefix('v1')->middleware('throttle:60,1')->controller(StoreController::class)->group(function () {
    Route::get('/store', 'showPublic')->name('public.store.show');
    Route::get('/store/products', 'publicProducts')->name('public.store.products');
    Route::get('/store/categories', 'publicCategories')->name('public.store.categories');
});
