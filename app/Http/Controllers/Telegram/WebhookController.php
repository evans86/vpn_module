<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\Salesman\Salesman;
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
     * Обработка webhook-а для главного бота
     *
     * @param Request $request
     * @param string $token
     * @return JsonResponse
     */
    public function fatherBot(Request $request, string $token): JsonResponse
    {
        try {
            Log::info('Received webhook for father bot', [
                'token' => substr($token, 0, 10) . '...',
                'request_body' => $request->getContent(),
                'headers' => $request->headers->all(),
                'source' => 'telegram'
            ]);

            // Проверяем, что переданный токен совпадает с токеном из конфигурации
            $configToken = config('telegram.father_bot.token');
            if ($token !== $configToken) {
                Log::error('Invalid bot token provided', [
                    'provided' => substr($token, 0, 10) . '...',
                    'expected' => substr($configToken, 0, 10) . '...',
                    'source' => 'telegram'
                ]);
                return response()->json(['status' => 'error', 'message' => 'Invalid token'], ResponseAlias::HTTP_FORBIDDEN);
            }

            $bot = new FatherBotController($token);
            $bot->init();

            return response()->json(['status' => 'success']);
        } catch (Exception $e) {
            Log::warning('!Error processing FATHER bot webhook!', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_body' => $request->getContent(),
                'source' => 'telegram'
            ]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Обработка webhook-а для бота продавца
     *
     * @param Request $request
     * @param string $token
     * @return JsonResponse
     */
    public function salesmanBot(Request $request, string $token): JsonResponse
    {
        try {
            // Проверяем существование продавца перед созданием контроллера (поиск учитывает старые зашифрованные токены)
            $salesman = Salesman::findByToken($token);
            if (!$salesman) {
                // Если продавец не найден, это может быть старый вебхук от удаленного/измененного бота
                // Возвращаем успешный ответ, чтобы Telegram не повторял запрос
                Log::warning('Salesman not found for webhook token', [
                    'token' => substr($token, 0, 10) . '...',
                    'source' => 'telegram',
                    'note' => 'This may be an old webhook from a deleted/changed bot. Returning success to prevent retries.'
                ]);
                return response()->json(['status' => 'ignored', 'message' => 'Salesman not found']);
            }

            Log::info('Received webhook for salesman bot', [
                'token' => substr($token, 0, 10) . '...',
                'salesman_id' => $salesman->id,
                'source' => 'telegram'
            ]);

            $bot = new SalesmanBotController($token);
            $bot->init();

            return response()->json(['status' => 'success']);
        } catch (Exception $e) {
            // Если это исключение "Salesman not found", логируем как warning
            if (strpos($e->getMessage(), 'Salesman not found') !== false) {
                Log::warning('Salesman bot webhook error: ' . $e->getMessage(), [
                    'token' => substr($token, 0, 10) . '...',
                    'source' => 'telegram'
                ]);
                return response()->json(['status' => 'ignored', 'message' => 'Salesman not found']);
            }

            // Для других ошибок логируем как error
            Log::error('Error processing salesman bot webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'token' => substr($token, 0, 10) . '...',
                'source' => 'telegram'
            ]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
