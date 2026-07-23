<?php

use App\Http\Controllers\Api\PublicStoreController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/**********************    Public Store Routes    ***********************/
Route::prefix('v1')->middleware('throttle:60,1')->controller(PublicStoreController::class)->group(function () {
    Route::get('/stores', 'index')->name('public.stores.index');
    Route::get('/stores/{slug}', 'show')->name('public.stores.show');
    Route::get('/stores/{slug}/products', 'products')->name('public.stores.products');
    Route::get('/stores/{slug}/categories', 'categories')->name('public.stores.categories');
    Route::get('/products/{id}', 'showProduct')->name('public.products.show');
});
