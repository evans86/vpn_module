<?php

use App\Http\Controllers\Module\ServerController;
use App\Http\Controllers\Module\PanelController;
use App\Http\Controllers\Module\SalesmanController;
use App\Http\Controllers\Module\PackController;
use App\Http\Controllers\Module\PackSalesmanController;
use App\Http\Controllers\Module\KeyActivateController;
use App\Http\Controllers\Module\BotController;
use App\Http\Controllers\VpnConfigController;
use App\Http\Controllers\LogController;
use App\Services\Telegram\ModuleBot\FatherBotController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Auth::routes(['register' => false]);

// Telegram Bot Webhook
Route::post('/telegram/webhook/{token}', [FatherBotController::class, 'handle'])->name('telegram.webhook');

// VPN Config Download
Route::get('/config/{token}', [VpnConfigController::class, 'show'])->name('vpn.config.show');

// Admin Routes
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware(['auth'])->group(function () {
        // Logs Routes (должны быть первыми, чтобы не перехватывались другими маршрутами)
        Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
        Route::get('/logs/{log}', [LogController::class, 'show'])->name('logs.show');

        // Server Routes
        Route::prefix('module/server')->name('module.server.')->group(function () {
            Route::get('/', [ServerController::class, 'index'])->name('index');
            Route::post('/', [ServerController::class, 'store'])->name('store');
            Route::put('/{server}', [ServerController::class, 'update'])->name('update');
            Route::delete('/{server}', [ServerController::class, 'destroy'])->name('destroy');
            Route::post('/{server}/toggle-status', [ServerController::class, 'toggleStatus'])->name('toggle-status');
        });

        // Panel Routes
        Route::prefix('module/panel')->name('module.panel.')->group(function () {
            Route::get('/', [PanelController::class, 'index'])->name('index');
            Route::get('/create', [PanelController::class, 'create'])->name('create');
            Route::post('/', [PanelController::class, 'store'])->name('store');
            Route::get('/{panel}/edit', [PanelController::class, 'edit'])->name('edit');
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
            Route::post('/{id}/assign-pack', [SalesmanController::class, 'assignPack'])->name('assign-pack');
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
            Route::post('/{key}/update-dates', [KeyActivateController::class, 'updateDates'])->name('update-dates');
        });

        // Bot Routes
        Route::prefix('module/bot')->name('module.bot.')->group(function () {
            Route::get('/', [BotController::class, 'index'])->name('index');
            Route::post('/update-token', [BotController::class, 'updateToken'])->name('update-token');
        });

        // Dashboard
        Route::get('/', function () {
            return redirect()->route('admin.module.server.index');
        })->name('dashboard');
    });
});

// Redirect root to admin panel if authenticated
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('admin.module.server.index');
    }
    return redirect()->route('login');
});
