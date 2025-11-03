<?php

use App\Http\Controllers\Module\NetworkCheckController;
use App\Http\Controllers\Module\PersonalController;
use App\Http\Controllers\Module\ServerController;
use App\Http\Controllers\Module\PanelController;
use App\Http\Controllers\Module\SalesmanController;
use App\Http\Controllers\Module\PackController;
use App\Http\Controllers\Module\PackSalesmanController;
use App\Http\Controllers\Module\KeyActivateController;
use App\Http\Controllers\Module\BotController;
use App\Http\Controllers\Module\ServerMonitoringController;
use App\Http\Controllers\Module\TelegramUserController;
use App\Http\Controllers\VpnConfigController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\Module\ServerUserController;
use App\Http\Controllers\Module\ServerUserTransferController;
use App\Http\Middleware\VerifyCsrfToken;
use App\Services\Telegram\ModuleBot\FatherBotController;
use App\Http\Controllers\Auth\Personal\SalesmanAuthController;
use App\Http\Controllers\PublicNetworkCheckController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Auth::routes(['register' => false]);

// Личный кабинет продавца
Route::prefix('personal')->name('personal.')->group(function () {
    // Авторизация через Telegram
    Route::get('/auth', [SalesmanAuthController::class, 'showLoginForm'])->name('auth');
    Route::get('/auth/telegram', [SalesmanAuthController::class, 'redirect'])->name('auth.telegram');
    Route::get('/auth/telegram/callback', [SalesmanAuthController::class, 'callback'])->name('auth.telegram.callback');

    // Защищенные маршруты
    Route::middleware(['auth:salesman'])->group(function () {
        Route::get('/dashboard', [PersonalController::class, 'dashboard'])->name('dashboard');
        Route::get('/keys', [PersonalController::class, 'keys'])->name('keys');
        Route::get('/packages', [PersonalController::class, 'packages'])->name('packages');

        // Проверка соединения
        Route::prefix('network-check')->name('network.')->group(function () {
            Route::get('/', [NetworkCheckController::class, 'index'])->name('index');
            Route::get('/ping', [NetworkCheckController::class, 'ping'])->name('ping'); // latency
            Route::get('/payload/{size}', [NetworkCheckController::class, 'payload'])
                ->where(['size' => '^[0-9]+(kb|mb|b)$'])
                ->name('payload')
                ->middleware('throttle:120,1'); // защита от ab/use

            Route::post('/report', [NetworkCheckController::class, 'report'])->name('report');
        });

        // FAQ и инструкции
        Route::prefix('faq')->group(function () {
            Route::get('/', [PersonalController::class, 'faq'])->name('faq');
            Route::post('/update', [PersonalController::class, 'updateFaq'])->name('faq.update');
            Route::post('/reset', [PersonalController::class, 'resetFaq'])->name('faq.reset');
            Route::post('/vpn-instructions/update', [PersonalController::class, 'updateVpnInstructions'])->name('faq.vpn-instructions.update');
            Route::post('/vpn-instructions/reset', [PersonalController::class, 'resetVpnInstructions'])->name('faq.vpn-instructions.reset');
        });
    });

    Route::post('/logout', function () {
        Auth::guard('salesman')->logout();
        return redirect()->route('personal.auth');
    })->name('logout');
});

// Telegram Bot Webhook
Route::post('/telegram/webhook/{token}', [FatherBotController::class, 'handle'])->name('telegram.webhook');

// VPN Config Download
Route::get('/config/{token}', [VpnConfigController::class, 'show'])->name('vpn.config.show');

// Public Network Check
Route::prefix('netcheck')->name('netcheck.')->group(function () {
    Route::get('/', [PublicNetworkCheckController::class, 'index'])->name('index');
    Route::get('/ping', [PublicNetworkCheckController::class, 'ping'])->name('ping')->middleware('throttle:180,1');
    Route::get('/payload/{size}', [PublicNetworkCheckController::class, 'payload'])
        ->where(['size' => '^[0-9]+(kb|mb|b)$'])
        ->name('payload')
        ->middleware('throttle:120,1');

    Route::post('/report', [PublicNetworkCheckController::class, 'report'])->name('report');

    // телеметрия чекпоинтов (без CSRF — нужно для sendBeacon)
    Route::post('/telemetry', [PublicNetworkCheckController::class, 'telemetry'])
        ->name('telemetry')
        ->withoutMiddleware([VerifyCsrfToken::class])
        ->middleware('throttle:240,1');
});

// Public Network Check - Simplified version
Route::prefix('netcheck')->name('netcheck.')->group(function () {
    Route::get('/simple', [PublicNetworkCheckController::class, 'index'])->name('simple');
    Route::get('/ping', [PublicNetworkCheckController::class, 'ping'])->name('ping')->middleware('throttle:180,1');
    Route::get('/payload/{size}', [PublicNetworkCheckController::class, 'payload'])
        ->where(['size' => '^[0-9]+(kb|mb|b)$'])
        ->name('payload')
        ->middleware('throttle:120,1');

    Route::post('/report', [PublicNetworkCheckController::class, 'report'])->name('report');

    // телеметрия чекпоинтов
    Route::post('/telemetry', [PublicNetworkCheckController::class, 'telemetry'])
        ->name('telemetry')
        ->withoutMiddleware([VerifyCsrfToken::class])
        ->middleware('throttle:240,1');
});

// Admin Routes
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware(['auth'])->group(function () {
        // Logs
        Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
        Route::get('/logs/{log}', [LogController::class, 'show'])->name('logs.show');

        // Модули
        Route::prefix('module')->name('module.')->group(function () {
            // Серверы
            Route::prefix('server')->name('server.')->group(function () {
                Route::get('/', [ServerController::class, 'index'])->name('index');
                Route::get('/{server}', [ServerController::class, 'show'])->name('show');
                Route::post('/', [ServerController::class, 'store'])->name('store');
                Route::put('/{server}', [ServerController::class, 'update'])->name('update');
                Route::delete('/{server}', [ServerController::class, 'destroy'])->name('destroy');
                Route::post('/{server}/toggle-status', [ServerController::class, 'toggleStatus'])->name('toggle-status');
            });

            // Панели
            Route::prefix('panel')->name('panel.')->group(function () {
                Route::get('/', [PanelController::class, 'index'])->name('index');
                Route::get('/{panel}', [PanelController::class, 'show'])->name('show');
                Route::get('/create', [PanelController::class, 'create'])->name('create');
                Route::post('/', [PanelController::class, 'store'])->name('store');
                Route::get('/{panel}/edit', [PanelController::class, 'edit'])->name('edit');
                Route::put('/{panel}', [PanelController::class, 'update'])->name('update');
                Route::post('/{panel}/configure', [PanelController::class, 'configure'])->name('configure');
                Route::post('/{panel}/update-config', [PanelController::class, 'updateConfig'])->name('update-config');
            });

            // Salesman
            Route::prefix('salesman')->name('salesman.')->group(function () {
                Route::get('/', [SalesmanController::class, 'index'])->name('index');
                Route::get('/{salesman}', [SalesmanController::class, 'show'])->name('show');
                Route::post('/', [SalesmanController::class, 'store'])->name('store');
                Route::put('/{salesman}', [SalesmanController::class, 'update'])->name('update');
                Route::delete('/{salesman}', [SalesmanController::class, 'destroy'])->name('destroy');
                Route::post('/{id}/toggle-status', [SalesmanController::class, 'toggleStatus'])->name('toggle-status');
                Route::post('/{id}/assign-pack', [SalesmanController::class, 'assignPack'])->name('assign-pack');
                Route::post('/{id}/assign-panel', [SalesmanController::class, 'assignPanel'])->name('assign-panel');
                Route::post('/{id}/reset-panel', [SalesmanController::class, 'resetPanel'])->name('reset-panel');
            });

            // Pack
            Route::prefix('pack')->name('pack.')->group(function () {
                Route::get('/', [PackController::class, 'index'])->name('index');
                Route::post('/', [PackController::class, 'store'])->name('store');
                Route::put('/{pack}', [PackController::class, 'update'])->name('update');
                Route::delete('/{pack}', [PackController::class, 'destroy'])->name('destroy');
            });

            // Pack-Salesman
            Route::prefix('pack-salesman')->name('pack-salesman.')->group(function () {
                Route::get('/', [PackSalesmanController::class, 'index'])->name('index');
                Route::post('/', [PackSalesmanController::class, 'store'])->name('store');
                Route::put('/{packSalesman}', [PackSalesmanController::class, 'update'])->name('update');
                Route::delete('/{packSalesman}', [PackSalesmanController::class, 'destroy'])->name('destroy');
                Route::post('/{id}/mark-as-paid', [PackSalesmanController::class, 'markAsPaid'])->name('mark-as-paid');
            });

            // Server User Transfer
            Route::prefix('server-user-transfer')->name('server-user-transfer.')->group(function () {
                Route::post('/panels', [ServerUserTransferController::class, 'getPanels'])->name('panels');
                Route::post('/transfer', [ServerUserTransferController::class, 'transfer'])->name('transfer');
            });

            // Key Activate
            Route::prefix('key-activate')->name('key-activate.')->group(function () {
                Route::get('/', [KeyActivateController::class, 'index'])->name('index');
                Route::delete('/{id}', [KeyActivateController::class, 'destroy'])->name('destroy');
                Route::post('/update-date', [KeyActivateController::class, 'updateDate'])->name('update-date');
            });

            // Server Monitoring
            Route::prefix('server-monitoring')->name('server-monitoring.')->group(function () {
                Route::get('/', [ServerMonitoringController::class, 'index'])->name('index');
                Route::get('/{panel_id?}', [ServerMonitoringController::class, 'index'])->name('index');
            });

            // Server Users
            Route::prefix('server-users')->name('server-users.')->group(function () {
                Route::get('/', [ServerUserController::class, 'index'])->name('index');
                Route::get('/{panel_id}', [ServerUserController::class, 'show'])->name('show');
            });

            // Bot
            Route::prefix('bot')->name('bot.')->group(function () {
                Route::get('/', [BotController::class, 'index'])->name('index');
                Route::post('/update-token', [BotController::class, 'updateToken'])->name('update-token');
            });

            // Telegram Users
            Route::prefix('telegram-users')->name('telegram-users.')->group(function () {
                Route::get('/', [TelegramUserController::class, 'index'])->name('index');
                Route::post('/{id}/toggle-status', [TelegramUserController::class, 'toggleStatus'])->name('toggle-status');
            });
        });

        // Dashboard
        Route::get('/', function () {
            return redirect()->route('admin.module.server.index');
        })->name('dashboard');
    });
});

// Redirect root
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('admin.module.server.index');
    }
    return redirect()->route('login');
});
