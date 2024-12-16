<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Module\ServerController;
use App\Http\Controllers\Telegram\WebhookController;

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
