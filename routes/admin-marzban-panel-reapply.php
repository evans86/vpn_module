<?php

/**
 * Маршрут «переприменить конфиг Marzban» подключается здесь отдельно от routes/web.php,
 * чтобы случайное слияние не отбросило строку в большом файле на проде.
 */
use App\Http\Controllers\Module\PanelController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware('admin.http_basic')->group(function () {
    Route::middleware(['auth'])->group(function () {
        Route::prefix('module')->name('module.')->group(function () {
            Route::prefix('panel')->name('panel.')->group(function () {
                Route::post('/{panel}/reapply-marzban-config', [PanelController::class, 'reapplyMarzbanConfiguration'])
                    ->name('reapply-marzban-config');
            });
        });
    });
});
