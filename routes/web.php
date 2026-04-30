<?php

use App\Http\Controllers\Module\ConnectionLimitViolationController;
use App\Http\Controllers\Module\NetworkCheckController;
use App\Http\Controllers\Module\PersonalController;
use App\Http\Controllers\Module\ServerController;
use App\Http\Controllers\Module\PanelController;
use App\Http\Controllers\Module\SalesmanController;
use App\Http\Controllers\Module\PackController;
use App\Http\Controllers\Module\PackSalesmanController;
use App\Http\Controllers\Module\KeyActivateController;
use App\Http\Controllers\Module\BotController;
use App\Http\Controllers\Module\ServerFleetHealthController;
use App\Http\Controllers\Module\ServerMonitoringController;
use App\Http\Controllers\Module\TelegramUserController;
use App\Http\Controllers\Module\VpnDirectDomainController;
use App\Http\Controllers\VpnConfigController;
use App\Http\Controllers\VpnDirectDomainsPublicController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\Module\ServerUserController;
use App\Http\Controllers\Module\ServerUserTransferController;
use App\Helpers\UrlHelper;
use App\Http\Middleware\VerifyCsrfToken;
use App\Services\Telegram\ModuleBot\FatherBotController;
use App\Http\Controllers\Auth\Personal\SalesmanAuthController;
use App\Http\Middleware\RedirectPersonalToConfigPublicHost;
use App\Http\Controllers\PublicNetworkCheckController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// HTTP Basic до формы Laravel: иначе при открытии /login сначала видна БД-форма, а Basic только на /admin.
Route::middleware(['admin.http_basic'])->group(function () {
    Auth::routes(['register' => false]);
});

/*
| ЛК: префикс /_lk/ (не /personal/…). Состояние меняем через GET + _token в query (VerifyCsrfToken).
| Prefetch GET без _token — см. VerifyCsrfToken::isLkPrefetchGet и редиректы в контроллерах.
| PDF отчёта проверки сети: тело JSON большое — только POST (fetch), не GET.
*/
Route::middleware([RedirectPersonalToConfigPublicHost::class])->group(function () {
    Route::get('_lk/auth/email', [SalesmanAuthController::class, 'loginWithEmail'])
        ->middleware('throttle:10,1')
        ->name('personal.auth.email');
});

Route::middleware([RedirectPersonalToConfigPublicHost::class, 'auth.salesman'])->group(function () {
    Route::get('_lk/cabinet-login/save', [PersonalController::class, 'updateCabinetLoginSettings'])
        ->name('personal.cabinet-login.update');
    Route::get('_lk/faq/save', [PersonalController::class, 'updateFaq'])->name('personal.faq.update');
    Route::get('_lk/faq/reset', [PersonalController::class, 'resetFaq'])->name('personal.faq.reset');
    Route::get('_lk/faq/vpn-instructions', [PersonalController::class, 'updateVpnInstructions'])->name('personal.faq.vpn-instructions.update');
    Route::get('_lk/faq/vpn-instructions/reset', [PersonalController::class, 'resetVpnInstructions'])->name('personal.faq.vpn-instructions.reset');
    Route::get('_lk/activation-message/save', [PersonalController::class, 'updateActivationSuccessMessage'])
        ->name('personal.activation-success.update');
    Route::get('_lk/activation-message/reset', [PersonalController::class, 'resetActivationSuccessMessage'])
        ->name('personal.activation-success.reset');
    Route::post('_lk/network-check/report', [NetworkCheckController::class, 'report'])
        ->middleware('throttle:120,1')
        ->name('personal.network.report');
});

Route::middleware([RedirectPersonalToConfigPublicHost::class])->group(function () {
    Route::get('_lk/logout', function () {
        $request = request();
        if (! $request->has('_token')) {
            return redirect()->to(UrlHelper::personalRoute('personal.auth'));
        }
        if (session()->has('impersonation_admin_id')) {
            $sid = session('impersonation_salesman_id');
            Auth::guard('salesman')->logout();
            session()->forget(['impersonation_admin_id', 'impersonation_salesman_id']);
            if ($sid) {
                return redirect()->route('admin.module.salesman.show', $sid)
                    ->with('success', 'Режим просмотра личного кабинета завершён.');
            }
        }
        Auth::guard('salesman')->logout();

        return redirect()->to(UrlHelper::personalRoute('personal.auth'));
    })->middleware('auth.salesman')->name('personal.logout');
});

// Личный кабинет продавца (канонический хост — APP_CONFIG_PUBLIC_URL)
Route::prefix('personal')
    ->middleware([RedirectPersonalToConfigPublicHost::class])
    ->name('personal.')
    ->group(function () {
    // Авторизация через Telegram
    Route::get('/auth', [SalesmanAuthController::class, 'showLoginForm'])->name('auth');
    Route::get('/auth/telegram', [SalesmanAuthController::class, 'redirect'])->name('auth.telegram');
    Route::get('/auth/telegram/callback', [SalesmanAuthController::class, 'callback'])->name('auth.telegram.callback');
    // Вход в ЛК по подписанной ссылке из админки (другой домен / APP_CONFIG_PUBLIC_URL)
    Route::get('/auth/impersonate', [SalesmanAuthController::class, 'impersonateConsume'])
        ->middleware('signed')
        ->name('auth.impersonate');

    // Защищенные маршруты (auth.salesman = SalesmanOnly, см. Kernel)
    Route::middleware(['auth.salesman'])->group(function () {
        Route::get('/dashboard', [PersonalController::class, 'dashboard'])->name('dashboard');
        Route::get('/keys', [PersonalController::class, 'keys'])->name('keys');
        Route::get('/cabinet-login', [PersonalController::class, 'cabinetLoginSettings'])->name('cabinet-login');
        Route::get('/packages', [PersonalController::class, 'packages'])->name('packages');

        // Проверка соединения
        Route::prefix('network-check')->name('network.')->group(function () {
            Route::get('/', [NetworkCheckController::class, 'index'])->name('index');
            Route::get('/ping', [NetworkCheckController::class, 'ping'])->name('ping'); // latency
            Route::get('/payload/{size}', [NetworkCheckController::class, 'payload'])
                ->where(['size' => '^[0-9]+(kb|mb|b)$'])
                ->name('payload')
                ->middleware('throttle:120,1'); // защита от ab/use
        });

        Route::prefix('faq')->group(function () {
            Route::get('/', [PersonalController::class, 'faq'])->name('faq');
            Route::get('/update', function () {
                return redirect()->route('personal.faq');
            });
        });

        Route::get('/activation-success', [PersonalController::class, 'activationSuccessMessage'])->name('activation-success');
    });
});

// Telegram Bot Webhook
Route::post('/telegram/webhook/{token}', [FatherBotController::class, 'handle'])->name('telegram.webhook');

// Service Worker для failover на зеркала (берёт список из APP_MIRROR_URLS). Код — resources/js/service-worker.js.
Route::get('/service-worker.js', function () {
    $mirrors = config('app.mirror_urls', []);
    if (empty($mirrors)) {
        return response('', 404);
    }
    $path = resource_path('js/service-worker.js');
    if (! is_readable($path)) {
        abort(500, 'resources/js/service-worker.js is missing');
    }
    $prefix = "'use strict';\nconst MIRROR_ORIGINS = "
        . json_encode(array_values($mirrors), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        . ";\n";
    $body = $prefix . file_get_contents($path);

    return response($body, 200, [
        'Content-Type' => 'application/javascript; charset=UTF-8',
        'Cache-Control' => 'no-store, must-revalidate',
    ]);
})->name('service-worker');

// VPN Error Page (только для локальной разработки) - должен быть ПЕРЕД /config/{token}
Route::get('/config/error', [VpnConfigController::class, 'showError'])->name('vpn.config.error');

// VPN Config Download (shell и подписка — с сессией web). /content и /refresh — routes/config-public.php (без сессии).
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

// Публичный JSON: домены «без VPN» (централизованный список из админки)
Route::get('/vpn/routing/direct-domains.json', [VpnDirectDomainsPublicController::class, 'json'])
    ->middleware('throttle:120,1')
    ->name('public.vpn.direct-domains');

// sing-box remote rule-set (source), см. https://sing-box.sagernet.org/configuration/rule-set/source-format/
Route::get('/vpn/routing/direct-domains-rule-set.json', [VpnDirectDomainsPublicController::class, 'ruleSetSource'])
    ->middleware('throttle:120,1')
    ->name('public.vpn.direct-domains-rule-set');

// Admin Routes (опционально: ADMIN_HTTP_BASIC_USER + ADMIN_HTTP_BASIC_PASSWORD в .env)
Route::prefix('admin')->name('admin.')->middleware('admin.http_basic')->group(function () {
    Route::middleware(['auth'])->group(function () {
        // Logs
        Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
        Route::get('/logs/{log}', [LogController::class, 'show'])->name('logs.show');

        // Модули
        Route::prefix('module')->name('module.')->group(function () {

            // Connection Limit Violations
            Route::prefix('connection-limit-violations')->name('connection-limit-violations.')->group(function () {
                Route::get('/', [ConnectionLimitViolationController::class, 'index'])->name('index');
                Route::post('/manual-check', [ConnectionLimitViolationController::class, 'manualCheck'])->name('manual-check');
                Route::post('/bulk-actions', [ConnectionLimitViolationController::class, 'bulkActions'])->name('bulk-actions');
                Route::post('/{violation}/manage', [ConnectionLimitViolationController::class, 'manageViolation'])->name('manage');
                Route::post('/{violation}/quick-action', [ConnectionLimitViolationController::class, 'quickAction'])->name('quick-action');
                Route::get('/{violation}', [ConnectionLimitViolationController::class, 'show'])->name('show');
                Route::post('/{violation}/resolve', [ConnectionLimitViolationController::class, 'resolve'])->name('resolve');
                Route::post('/{violation}/ignore', [ConnectionLimitViolationController::class, 'ignore'])->name('ignore');
            });

            // Серверы
            Route::prefix('server')->name('server.')->group(function () {
                Route::get('/', [ServerController::class, 'index'])->name('index');
                Route::get('/{server}', [ServerController::class, 'show'])->name('show');
                Route::post('/', [ServerController::class, 'store'])->name('store');
                Route::post('/store-manual', [ServerController::class, 'storeManual'])->name('store-manual');
                Route::post('/{server}/setup-dns', [ServerController::class, 'setupDns'])->name('setup-dns');
                Route::post('/{server}/ping-and-configure', [ServerController::class, 'pingAndConfigure'])->name('ping-and-configure');
                Route::post('/{server}/reboot', [ServerController::class, 'reboot'])->name('reboot');
                Route::put('/{server}', [ServerController::class, 'update'])->name('update');
                Route::delete('/{server}', [ServerController::class, 'destroy'])->name('destroy');
                Route::post('/{server}/toggle-status', [ServerController::class, 'toggleStatus'])->name('toggle-status');
                Route::post('/{server}/enable-log-upload', [ServerController::class, 'enableLogUpload'])->name('enable-log-upload');
                Route::get('/{server}/check-log-upload-status', [ServerController::class, 'checkLogUploadStatus'])->name('check-log-upload-status');
                Route::post('/{server}/apply-decoy-stub', [ServerController::class, 'applyDecoyStub'])->name('apply-decoy-stub');
            });

            Route::prefix('server-fleet')->name('server-fleet.')->group(function () {
                Route::get('/report', [ServerFleetHealthController::class, 'index'])->name('report');
                Route::post('/report/run', [ServerFleetHealthController::class, 'run'])
                    ->middleware('throttle:8,1')
                    ->name('report.run');
            });

            // Панели
            Route::prefix('panel')->name('panel.')->group(function () {
                Route::get('/', [PanelController::class, 'index'])->name('index');
                Route::get('/{panel}', [PanelController::class, 'show'])->name('show');
                Route::get('/create', [PanelController::class, 'create'])->name('create');
                Route::post('/', [PanelController::class, 'store'])->name('store');
                Route::get('/{panel}/edit', [PanelController::class, 'edit'])->name('edit');
                Route::put('/{panel}', [PanelController::class, 'update'])->name('update');
                Route::delete('/{panel}', [PanelController::class, 'destroy'])->name('destroy');
                Route::post('/{panel}/configure', [PanelController::class, 'configure'])->name('configure');
                Route::post('/{panel}/update-config', [PanelController::class, 'updateConfig'])->name('update-config');
                Route::post('/{panel}/update-config-stable', [PanelController::class, 'updateConfigStable'])->name('update-config-stable');
                Route::post('/{panel}/update-config-reality', [PanelController::class, 'updateConfigReality'])->name('update-config-reality');
                Route::post('/{panel}/update-config-reality-stable', [PanelController::class, 'updateConfigRealityStable'])->name('update-config-reality-stable');
                Route::post('/{panel}/update-config-mixed', [PanelController::class, 'updateConfigMixed'])->name('update-config-mixed');
                Route::post('/{panel}/warp-routing', [PanelController::class, 'updateWarpRouting'])->name('update-warp-routing');
                Route::post('/{panel}/toggle-warp-routing', [PanelController::class, 'toggleWarpRouting'])->name('toggle-warp-routing');
                Route::post('/{panel}/check-warp-socks', [PanelController::class, 'checkWarpSocks'])->name('check-warp-socks');
                Route::post('/{panel}/import-warp-wireguard-snapshot', [PanelController::class, 'importWarpWireGuardSnapshot'])->name('import-warp-wireguard-snapshot');
                Route::post('/{panel}/install-warp-socks', [PanelController::class, 'installWarpSocksOnServer'])->name('install-warp-socks');
                Route::post('/{panel}/get-letsencrypt-certificate', [PanelController::class, 'getLetsEncryptCertificate'])->name('get-letsencrypt-certificate');
                Route::delete('/{panel}/remove-certificates', [PanelController::class, 'removeCertificates'])->name('remove-certificates');
                Route::post('/{panel}/toggle-tls', [PanelController::class, 'toggleTls'])->name('toggle-tls');
                Route::post('/{panel}/toggle-rotation-exclusion', [PanelController::class, 'toggleRotationExclusion'])->name('toggle-rotation-exclusion');
                Route::get('/{panel}/test-connection', [PanelController::class, 'testConnection'])->name('test-connection');
            });

            // Было: «Статистика панелей» — редирект на единую страницу распределения
            Route::redirect('panel-statistics', 'panel-distribution', 301);
            Route::redirect('panel-statistics/export-pdf', 'panel-distribution', 301);

            Route::prefix('panel-settings')->name('panel-settings.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Module\PanelSettingsController::class, 'index'])->name('index');
                Route::post('/clear-error', [\App\Http\Controllers\Module\PanelSettingsController::class, 'clearPanelError'])->name('clear-error');
            });

            Route::prefix('panel-distribution')->name('panel-distribution.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Module\PanelDistributionController::class, 'index'])->name('index');
            });

            // Salesman
            Route::prefix('salesman')->name('salesman.')->group(function () {
                Route::get('/', [SalesmanController::class, 'index'])->name('index');
                Route::get('/{salesman}/impersonate', [SalesmanController::class, 'impersonatePersonalCabinet'])
                    ->middleware('admin')
                    ->name('impersonate');
                Route::get('/{salesman}', [SalesmanController::class, 'show'])->name('show');
                Route::post('/', [SalesmanController::class, 'store'])->name('store');
                Route::put('/{salesman}', [SalesmanController::class, 'update'])->name('update');
                Route::delete('/{salesman}', [SalesmanController::class, 'destroy'])->name('destroy');
                Route::post('/{id}/toggle-status', [SalesmanController::class, 'toggleStatus'])->name('toggle-status');
                Route::post('/{id}/assign-pack', [SalesmanController::class, 'assignPack'])->name('assign-pack');
                Route::post('/{id}/assign-panel', [SalesmanController::class, 'assignPanel'])->name('assign-panel');
                Route::post('/{id}/reset-panel', [SalesmanController::class, 'resetPanel'])->name('reset-panel');
                Route::post('/{id}/update-bot-token', [SalesmanController::class, 'updateBotToken'])->name('update-bot-token');
                Route::post('/{id}/update-module', [SalesmanController::class, 'updateModule'])->name('update-module');
            });

            // Pack
            Route::prefix('pack')->name('pack.')->group(function () {
                Route::get('/', [PackController::class, 'index'])->name('index');
                Route::post('/', [PackController::class, 'store'])->name('store');
                Route::put('/{id}', [PackController::class, 'update'])->name('update');
                Route::delete('/{id}', [PackController::class, 'destroy'])->name('destroy');
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
                Route::get('/mass-transfer', [ServerUserTransferController::class, 'massTransferPage'])->name('mass-transfer');
                Route::post('/mass-transfer/key-count', [ServerUserTransferController::class, 'massTransferKeyCount'])->name('mass-transfer.key-count');
                Route::post('/mass-transfer/run', [ServerUserTransferController::class, 'massTransfer'])->name('mass-transfer.run');
                Route::post('/mass-transfer/run-batch', [ServerUserTransferController::class, 'massTransferBatch'])->name('mass-transfer.run-batch');
                Route::post('/balance/stats', [ServerUserTransferController::class, 'balanceStats'])->name('balance.stats');
                Route::post('/balance/step', [ServerUserTransferController::class, 'balanceStep'])->name('balance.step');
                Route::post('/multi-provider-migration/count', [ServerUserTransferController::class, 'multiProviderMigrationCount'])->name('multi-provider-migration.count');
                Route::post('/multi-provider-migration/check-key', [ServerUserTransferController::class, 'multiProviderMigrationCheckKey'])->name('multi-provider-migration.check-key');
                Route::post('/multi-provider-migration/single-key', [ServerUserTransferController::class, 'multiProviderMigrationSingleKey'])->name('multi-provider-migration.single-key');
                Route::post('/multi-provider-migration/run-batch', [ServerUserTransferController::class, 'multiProviderMigrationBatch'])->name('multi-provider-migration.run-batch');
                Route::post('/multi-provider-migration/start', [ServerUserTransferController::class, 'multiProviderMigrationStart'])->name('multi-provider-migration.start');
                Route::post('/multi-provider-migration/cancel', [ServerUserTransferController::class, 'multiProviderMigrationCancel'])->name('multi-provider-migration.cancel');
                Route::get('/multi-provider-migration/status', [ServerUserTransferController::class, 'multiProviderMigrationStatus'])->name('multi-provider-migration.status');
                Route::post('/key-slots', [ServerUserTransferController::class, 'getKeySlots'])->name('key-slots');
                Route::post('/transfer-data', [ServerUserTransferController::class, 'getTransferData'])->name('transfer-data');
                Route::post('/panels', [ServerUserTransferController::class, 'getPanels'])->name('panels');
                Route::post('/transfer', [ServerUserTransferController::class, 'transfer'])->name('transfer');
            });

            // Key Activate
            Route::prefix('key-activate')->name('key-activate.')->group(function () {
                Route::get('/', [KeyActivateController::class, 'index'])->name('index');
                Route::post('/{id}/deactivate', [KeyActivateController::class, 'deactivate'])->name('deactivate');
                Route::delete('/{id}', [KeyActivateController::class, 'destroy'])->name('destroy');
                Route::post('/update-date', [KeyActivateController::class, 'updateDate'])->name('update-date');
                Route::post('/renew', [KeyActivateController::class, 'renew'])->name('renew');
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

            // Orders
            Route::prefix('order')->name('order.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Module\OrderController::class, 'index'])->name('index');
                Route::get('/{id}', [\App\Http\Controllers\Module\OrderController::class, 'show'])->name('show');
                Route::post('/{id}/approve', [\App\Http\Controllers\Module\OrderController::class, 'approve'])->name('approve');
                Route::post('/{id}/reject', [\App\Http\Controllers\Module\OrderController::class, 'reject'])->name('reject');
                Route::delete('/{id}', [\App\Http\Controllers\Module\OrderController::class, 'destroy'])->name('destroy');
            });

            // Payment Methods
            Route::prefix('payment-method')->name('payment-method.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Module\PaymentMethodController::class, 'index'])->name('index');
                Route::post('/', [\App\Http\Controllers\Module\PaymentMethodController::class, 'store'])->name('store');
                Route::put('/{id}', [\App\Http\Controllers\Module\PaymentMethodController::class, 'update'])->name('update');
                Route::delete('/{id}', [\App\Http\Controllers\Module\PaymentMethodController::class, 'destroy'])->name('destroy');
            });

            // Order Settings
            Route::prefix('order-settings')->name('order-settings.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Module\OrderSettingsController::class, 'index'])->name('index');
                Route::post('/', [\App\Http\Controllers\Module\OrderSettingsController::class, 'update'])->name('update');
            });

            // Рассылки
            Route::prefix('broadcast')->name('broadcast.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Module\BroadcastController::class, 'index'])->name('index');
                Route::get('/create', [\App\Http\Controllers\Module\BroadcastController::class, 'create'])->name('create');
                Route::post('/', [\App\Http\Controllers\Module\BroadcastController::class, 'store'])->name('store');
                Route::get('/search-users', [\App\Http\Controllers\Module\BroadcastController::class, 'searchUsers'])->name('search-users');
                Route::get('/{broadcast}', [\App\Http\Controllers\Module\BroadcastController::class, 'show'])->name('show');
                Route::post('/{broadcast}/start', [\App\Http\Controllers\Module\BroadcastController::class, 'start'])->name('start');
                Route::post('/{broadcast}/cancel', [\App\Http\Controllers\Module\BroadcastController::class, 'cancel'])->name('cancel');
                Route::post('/{broadcast}/test-send', [\App\Http\Controllers\Module\BroadcastController::class, 'testSend'])->name('test-send');
            });

            // Домены без VPN (Direct) — список для клиентов с remote rules
            Route::prefix('vpn-direct-domains')->name('vpn-direct-domains.')->group(function () {
                Route::get('/', [VpnDirectDomainController::class, 'index'])->name('index');
                Route::post('/', [VpnDirectDomainController::class, 'store'])->name('store');
                Route::get('/{vpnDirectDomain}/edit', [VpnDirectDomainController::class, 'edit'])->name('edit');
                Route::put('/{vpnDirectDomain}', [VpnDirectDomainController::class, 'update'])->name('update');
                Route::delete('/{vpnDirectDomain}', [VpnDirectDomainController::class, 'destroy'])->name('destroy');
                Route::post('/{vpnDirectDomain}/toggle', [VpnDirectDomainController::class, 'toggle'])->name('toggle');
            });

            // Очередь заданий (перевыпуск ключей, рассылки, миграции)
            Route::prefix('queue')->name('queue.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Module\QueueMonitorController::class, 'index'])->name('index');
                Route::post('/retry', [\App\Http\Controllers\Module\QueueMonitorController::class, 'retry'])->name('retry');
                Route::post('/retry-all', [\App\Http\Controllers\Module\QueueMonitorController::class, 'retryAll'])->name('retry-all');
                Route::post('/flush', [\App\Http\Controllers\Module\QueueMonitorController::class, 'flush'])->name('flush');
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
