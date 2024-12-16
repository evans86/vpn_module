<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Services\Telegram\ModuleBot\FatherBotController;
use App\Services\Telegram\ModuleBot\SalesmanBotController;
use Exception;
use Illuminate\Http\JsonResponse;
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
    public function fatherBot(Request $request, string $token): JsonResponse
    {
        try {
            Log::debug('Received webhook for father bot', [
                'token' => substr($token, 0, 10) . '...',
                'request_body' => $request->getContent(),
                'headers' => $request->headers->all()
            ]);

            // Проверяем, что переданный токен совпадает с токеном из конфигурации
            $configToken = config('telegram.father_bot.token');
            if ($token !== $configToken) {
                Log::error('Invalid bot token provided', [
                    'provided' => substr($token, 0, 10) . '...',
                    'expected' => substr($configToken, 0, 10) . '...'
                ]);
                return response()->json(['status' => 'error', 'message' => 'Invalid token'], ResponseAlias::HTTP_FORBIDDEN);
            }

            $bot = new FatherBotController($token);
            $bot->init();

            return response()->json(['status' => 'success']);
        } catch (Exception $e) {
            Log::error('Error processing father bot webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_body' => $request->getContent()
            ]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Обработка webhook-а для бота продавца
     */
    public function salesmanBot(Request $request, string $token): JsonResponse
    {
        try {
            Log::debug('Received webhook for salesman bot', [
                'token' => substr($token, 0, 10) . '...',
                'request_body' => $request->getContent(),
                'headers' => $request->headers->all()
            ]);

            $bot = new SalesmanBotController($token);
            $bot->init();

            return response()->json(['status' => 'success']);
        } catch (Exception $e) {
            Log::error('Error processing salesman bot webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_body' => $request->getContent()
            ]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
