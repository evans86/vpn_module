<?php

use App\Http\Controllers\VpnConfigController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Конфиг: JSON/HTML фрагменты без web-сессии (быстрее, нет lock на session при fetch после shell)
|--------------------------------------------------------------------------
*/
Route::get('/config/{token}/content', [VpnConfigController::class, 'showConfigContent'])->name('vpn.config.content');
Route::get('/config/{token}/refresh', [VpnConfigController::class, 'showConfigRefresh'])->name('vpn.config.refresh');
