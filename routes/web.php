<?php

use App\Http\Controllers\Module\ServerController;
use App\Http\Controllers\Module\PanelController;
use App\Http\Controllers\Module\SalesmanController;
use App\Http\Controllers\Module\PackController;
use App\Http\Controllers\Module\PackSalesmanController;
use App\Http\Controllers\Module\KeyActivateController;
use App\Http\Controllers\Module\BotController;
use App\Http\Controllers\VpnConfigController;
use App\Services\Telegram\ModuleBot\FatherBotController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Authentication Routes
Route::get('/login', [App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
Route::post('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');

// Public routes
Route::get('/config/{key_activate_id}', [VpnConfigController::class, 'show'])
    ->name('vpn.config');

// Admin Routes
Route::middleware(['auth'])->group(function () {
    // Server Routes
    Route::prefix('module/server')->name('module.server.')->group(function () {
        Route::get('/', [ServerController::class, 'index'])->name('index');
        Route::post('/', [ServerController::class, 'store'])->name('store');
        Route::put('/{server}', [ServerController::class, 'update'])->name('update');
        Route::delete('/{server}', [ServerController::class, 'destroy'])->name('destroy');
        Route::get('/{server}/status', [ServerController::class, 'getStatus'])->name('status');
    });

    // Panel Routes
    Route::prefix('module/panel')->name('module.panel.')->group(function () {
        Route::get('/', [PanelController::class, 'index'])->name('index');
        Route::post('/', [PanelController::class, 'store'])->name('store');
        Route::put('/{panel}', [PanelController::class, 'update'])->name('update');
        Route::post('/{panel}/configure', [PanelController::class, 'configure'])->name('configure');
        Route::post('/{panel}/update-config', [PanelController::class, 'updateConfig'])->name('update-config');
        Route::get('/{panel}/status', [PanelController::class, 'checkStatus'])->name('status');
    });

    // Salesman Routes
    Route::prefix('module/salesman')->name('module.salesman.')->group(function () {
        Route::get('/', [SalesmanController::class, 'index'])->name('index');
        Route::post('/', [SalesmanController::class, 'store'])->name('store');
        Route::put('/{salesman}', [SalesmanController::class, 'update'])->name('update');
        Route::delete('/{salesman}', [SalesmanController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/toggle-status', [SalesmanController::class, 'toggleStatus'])->name('toggle-status');
    });

    // Pack Routes
    Route::prefix('module/pack')->name('module.pack.')->group(function () {
        Route::get('/', [PackController::class, 'index'])->name('index');
        Route::post('/', [PackController::class, 'store'])->name('store');
        Route::put('/{pack}', [PackController::class, 'update'])->name('update');
        Route::delete('/{pack}', [PackController::class, 'destroy'])->name('destroy');
    });

    // Pack Salesman Routes
    Route::prefix('module/pack-salesman')->name('module.pack-salesman.')->group(function () {
        Route::get('/', [PackSalesmanController::class, 'index'])->name('index');
        Route::post('/', [PackSalesmanController::class, 'store'])->name('store');
        Route::put('/{packSalesman}', [PackSalesmanController::class, 'update'])->name('update');
        Route::delete('/{packSalesman}', [PackSalesmanController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/mark-as-paid', [PackSalesmanController::class, 'markAsPaid'])->name('mark-as-paid');
    });

    // Key Activate Routes
    Route::prefix('module/key-activate')->name('module.key-activate.')->group(function () {
        Route::get('/', [KeyActivateController::class, 'index'])->name('index');
        Route::delete('/{key}', [KeyActivateController::class, 'destroy'])->name('destroy');
        Route::post('/{key}/test-activate', [KeyActivateController::class, 'testActivate'])->name('test-activate');
    });

    // Bot Routes
    Route::prefix('module/bot')->name('module.bot.')->group(function () {
        Route::get('/', [BotController::class, 'index'])->name('index');
        Route::post('/update-token', [BotController::class, 'updateToken'])->name('update-token');
    });

    // Father Bot Routes
    Route::prefix('module/father-bot')->name('module.father-bot.')->group(function () {
        Route::get('/', [FatherBotController::class, 'index'])->name('index');
        Route::post('/', [FatherBotController::class, 'store'])->name('store');
        Route::put('/{fatherBot}', [FatherBotController::class, 'update'])->name('update');
        Route::delete('/{fatherBot}', [FatherBotController::class, 'destroy'])->name('destroy');
    });

    // Маршруты для логов
    Route::prefix('logs')->name('logs.')->group(function () {
        Route::get('/', [App\Http\Controllers\LogController::class, 'index'])->name('index');
        Route::get('/{log}', [App\Http\Controllers\LogController::class, 'show'])->name('show');
    });

    // Dashboard
    Route::get('/', function () {
        return view('dashboard');
    })->name('dashboard');
});

// Redirect root to admin panel if authenticated
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('module.server.index');
    }
    return redirect()->route('login');
});
