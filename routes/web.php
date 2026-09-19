<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect(url('/docs/index.html'));
});
