<?php

use Illuminate\Support\Facades\Route;
use Splitfire\ApiGeo\Http\SearchProxyController;

Route::prefix((string) config('api-geo.proxy_prefix'))
    ->middleware(config('api-geo.proxy_middleware'))
    ->name('api-geo.search')
    ->get('search', SearchProxyController::class);
