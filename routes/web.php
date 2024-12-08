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
Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/config/{key_activate_id}', [VpnConfigController::class, 'show'])
    ->name('vpn.config');

// Admin Routes
Route::middleware(['auth', 'admin'])->group(function () {
    Route::prefix('admin')->group(function () {
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
        });

        // Key Activate Routes
        Route::prefix('module/key-activate')->name('module.key-activate.')->group(function () {
            Route::get('/', [KeyActivateController::class, 'index'])->name('index');
            Route::delete('/{key}', [KeyActivateController::class, 'destroy'])->name('destroy');
            Route::post('/{key}/test-activate', [KeyActivateController::class, 'testActivate'])->name('test-activate');
        });

        // Key Activation Management Routes
        Route::prefix('module/key-activate')->name('module.key-activate.')->group(function () {
            Route::get('/', [KeyActivateController::class, 'index'])->name('index');
            Route::delete('/{key}', [KeyActivateController::class, 'destroy'])->name('destroy');
        });

        // Bot Routes
        Route::prefix('module/bot')->name('module.bot.')->group(function () {
            Route::get('/', [BotController::class, 'index'])->name('index');
            Route::post('/', [BotController::class, 'store'])->name('store');
            Route::put('/{bot}', [BotController::class, 'update'])->name('update');
            Route::delete('/{bot}', [BotController::class, 'destroy'])->name('destroy');
        });

        // Father Bot Routes
        Route::prefix('module/father-bot')->name('module.father-bot.')->group(function () {
            Route::get('/', [FatherBotController::class, 'index'])->name('index');
            Route::post('/', [FatherBotController::class, 'store'])->name('store');
            Route::put('/{fatherBot}', [FatherBotController::class, 'update'])->name('update');
            Route::delete('/{fatherBot}', [FatherBotController::class, 'destroy'])->name('destroy');
        });

        // Panel Routes
        Route::prefix('panel')->name('panel.')->group(function () {
            Route::post('/', [PanelController::class, 'index'])->name('index');
            Route::post('/', [PanelController::class, 'store'])->name('store');
            Route::post('/{panel}/configure', [PanelController::class, 'configure'])->name('configure');
            Route::post('/{panel}/update-config', [PanelController::class, 'updateConfig'])->name('update-config');
        });

        // Маршруты для логов
        Route::get('/logs', [App\Http\Controllers\LogController::class, 'index'])->name('logs.index');
        Route::get('/logs/{log}', [App\Http\Controllers\LogController::class, 'show'])->name('logs.show');

        // Dashboard
        Route::get('/', function () {
            return view('dashboard');
        })->name('dashboard');
    });
});

Route::prefix('admin')->middleware(['auth'])->group(function () {
    Route::prefix('module')->group(function () {
        Route::prefix('pack-salesman')->group(function () {
            Route::get('/', [PackSalesmanController::class, 'index'])->name('module.pack-salesman.index');
            Route::post('/{id}/mark-as-paid', [PackSalesmanController::class, 'markAsPaid'])->name('module.pack-salesman.mark-as-paid');
        });
    });
});

// Redirect root to admin panel if authenticated
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('module.server.index');
    }
    return redirect()->route('login');
});
