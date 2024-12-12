<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Telegram\WebhookController;

// Маршруты для webhook-ов Telegram ботов
Route::post('/father-bot/{token}/init', [WebhookController::class, 'fatherBot']);
Route::post('/salesman-bot/{token}/init', [WebhookController::class, 'salesmanBot']);
