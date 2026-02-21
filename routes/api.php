<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Module\ServerController;
use App\Http\Controllers\Telegram\WebhookController;
use App\Http\Controllers\Api\v1\BotModuleController;
use App\Http\Controllers\Api\v1\KeyActivateController;
use App\Http\Controllers\Api\v1\PackSalesmanController;
use App\Http\Controllers\Api\v1\BotTWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('servers')->group(function () {
    Route::post('create', [ServerController::class, 'store']);
    Route::get('{server}/status', [ServerController::class, 'getStatus']);
    Route::delete('{server}', [ServerController::class, 'destroy']);
});

// Telegram Webhook Routes
Route::prefix('telegram')->group(function () {
    Route::post('father-bot/{token}/init', [WebhookController::class, 'fatherBot']);
    Route::post('salesman-bot/{token}/init', [WebhookController::class, 'salesmanBot']);
});

// Bot Module API Routes
Route::prefix('v1/bot-module')->group(function () {
    Route::get('ping', [BotModuleController::class, 'ping']);
    Route::get('create', [BotModuleController::class, 'create']);
    Route::get('get', [BotModuleController::class, 'get']);
    Route::get('settings', [BotModuleController::class, 'getSettings']);
    Route::post('update', [BotModuleController::class, 'update']);
    Route::get('delete', [BotModuleController::class, 'delete']);
});

// Key Activate API Routes
Route::prefix('v1/key-activate')->group(function () {
    Route::post('buy-key', [KeyActivateController::class, 'buyKey']); // ?
    Route::post('free-key', [KeyActivateController::class, 'getFreeKey']); // ?
    Route::get('user-key', [KeyActivateController::class, 'getUserKey']); // +-
    Route::get('user-keys', [KeyActivateController::class, 'getUserKeys']); // +-
    Route::get('vpn-instructions', [KeyActivateController::class, 'getVpnInstructions']); // +-
});

// BOT-T Webhook Routes
Route::prefix('v1/bott')->group(function () {
    // URL вебхука (уведомление после оплаты заказа)
    Route::post('webhook/order-payment', [BotTWebhookController::class, 'handleOrderPayment']);
    
    // URL вебхука (проверка товара перед выдачей клиенту)
    Route::post('webhook/validate-product', [BotTWebhookController::class, 'validateProduct']);
});
