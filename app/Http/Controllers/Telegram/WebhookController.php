<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Services\Telegram\ModuleBot\FatherBotController;
use App\Services\Telegram\ModuleBot\SalesmanBotController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class WebhookController extends Controller
{
    /**
     * Валидация webhook запроса
     */
    private function validateWebhookRequest(Request $request): bool
    {
        $secretToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
        return hash_equals(config('telegram.webhook_secret'), $secretToken);
    }

    /**
     * Обработка webhook-а для главного бота
     */
    public function fatherBot(Request $request, string $token): \Illuminate\Http\JsonResponse
    {
        try {
            if (!$this->validateWebhookRequest($request)) {
                return response()->json(['status' => 'error', 'message' => 'Invalid secret token'], ResponseAlias::HTTP_FORBIDDEN);
            }

            $bot = new FatherBotController($token);
            $bot->init();
            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Father bot webhook error: ' . $e->getMessage());
            return response()->json(['status' => 'error'], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Обработка webhook-а для бота продавца
     */
    public function salesmanBot(Request $request, string $token): \Illuminate\Http\JsonResponse
    {
        try {
            if (!$this->validateWebhookRequest($request)) {
                return response()->json(['status' => 'error', 'message' => 'Invalid secret token'], ResponseAlias::HTTP_FORBIDDEN);
            }

            $bot = new SalesmanBotController($token);
            $bot->init();
            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Salesman bot webhook error: ' . $e->getMessage());
            return response()->json(['status' => 'error'], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
