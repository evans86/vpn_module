<?php

namespace App\Services\Notification;

use App\Services\External\BottApi;
use App\Dto\Bot\BotModuleFactory;
use App\Dto\Notification\NotificationResult;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Salesman\Salesman;
use Telegram\Bot\Api;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    /**
     * Отправка сообщения пользователю через правильный канал
     * @deprecated Используйте sendToUserWithResult для получения детальной информации
     */
    public function sendToUser(KeyActivate $keyActivate, string $message, array $keyboard = null): bool
    {
        $result = $this->sendToUserWithResult($keyActivate, $message, $keyboard);
        return $result->shouldCountAsSent;
    }

    /**
     * Отправка сообщения пользователю с детальным результатом
     */
    public function sendToUserWithResult(KeyActivate $keyActivate, string $message, array $keyboard = null): NotificationResult
    {
        try {
            $userTgId = $keyActivate->user_tg_id;

            if (!$userTgId) {
                Log::warning('Cannot send notification: user not found', [
                    'key_id' => $keyActivate->id
                ]);
                return NotificationResult::userNotFound();
            }

            // Определяем способ отправки (модуль или обычный бот)
            if (!is_null($keyActivate->module_salesman_id)) {
                // Отправка через модуль
                return $this->sendViaModuleWithResult($keyActivate, $userTgId, $message, $keyboard);
            } else if (!is_null($keyActivate->pack_salesman_id)) {
                // Отправка через обычного бота продавца
                return $this->sendViaSalesmanBotWithResult($keyActivate, $userTgId, $message, $keyboard);
            } else {
                Log::warning('Cannot determine notification channel: no salesman assigned', [
                    'key_id' => $keyActivate->id,
                    'module_salesman_id' => $keyActivate->module_salesman_id,
                    'pack_salesman_id' => $keyActivate->pack_salesman_id
                ]);
                return NotificationResult::technicalError('No salesman assigned');
            }

        } catch (\Exception $e) {
            Log::error('Failed to send notification to user', [
                'key_id' => $keyActivate->id,
                'user_tg_id' => $keyActivate->user_tg_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return NotificationResult::technicalError($e->getMessage());
        }
    }

    /**
     * Отправка сообщения продавцу
     */
    public function sendToSalesman(Salesman $salesman, string $message, array $keyboard = null): bool
    {
        try {
            if (is_null($salesman->telegram_id)) {
                Log::warning('Cannot send notification: salesman telegram_id not found', [
                    'salesman_id' => $salesman->id
                ]);
                return false;
            }

            // Для продавца всегда используем FatherBot
            return $this->sendViaFatherBot($salesman->telegram_id, $message, $keyboard);

        } catch (\Exception $e) {
            Log::error('Failed to send notification to salesman', [
                'salesman_id' => $salesman->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Отправка через модуль бота (старый метод для обратной совместимости)
     */
    private function sendViaModule(KeyActivate $keyActivate, int $userTgId, string $message, array $keyboard = null): bool
    {
        $result = $this->sendViaModuleWithResult($keyActivate, $userTgId, $message, $keyboard);
        return $result->shouldCountAsSent;
    }

    /**
     * Отправка через модуль бота с детальным результатом
     */
    private function sendViaModuleWithResult(KeyActivate $keyActivate, int $userTgId, string $message, array $keyboard = null): NotificationResult
    {
        $salesman = $keyActivate->moduleSalesman;
        if (!$salesman || !$salesman->botModule) {
            Log::warning('No module found for notification', [
                'key_id' => $keyActivate->id,
                'salesman_id' => $keyActivate->module_salesman_id
            ]);
            return NotificationResult::technicalError('No module found');
        }

        try {
            $result = BottApi::senModuleMessage(
                BotModuleFactory::fromEntity($salesman->botModule),
                $userTgId,
                $message
            );

            // Проверяем результат отправки
            if (isset($result['result']) && $result['result'] === true) {
                return NotificationResult::success();
            }

            if (isset($result['success']) && $result['success'] === true) {
                return NotificationResult::success();
            }

            // Проверяем на ошибки в ответе
            if (isset($result['error'])) {
                $errorMessage = is_string($result['error']) ? $result['error'] : json_encode($result['error']);
                
                // Проверяем на блокировку бота
                if ($this->isBlockedError($errorMessage)) {
                    Log::warning('Chat not found - user may have blocked bot or deleted chat', [
                        'key_id' => $keyActivate->id,
                        'user_tg_id' => $userTgId,
                        'error' => $errorMessage
                    ]);
                    return NotificationResult::blocked($errorMessage);
                }
                
                Log::warning('Error in BottApi response', [
                    'key_id' => $keyActivate->id,
                    'user_tg_id' => $userTgId,
                    'error' => $errorMessage
                ]);
                return NotificationResult::technicalError($errorMessage);
            }

            // Если структура ответа другая, логируем для отладки
            Log::warning('Unexpected response format from BottApi::senModuleMessage', [
                'key_id' => $keyActivate->id,
                'user_tg_id' => $userTgId,
                'response' => $result
            ]);

            return NotificationResult::technicalError('Unexpected response format');

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Обрабатываем специфичные ошибки
            if ($this->isBlockedError($errorMessage)) {
                Log::warning('Chat not found - user may have blocked bot or deleted chat', [
                    'key_id' => $keyActivate->id,
                    'user_tg_id' => $userTgId,
                    'error' => $errorMessage
                ]);
                return NotificationResult::blocked($errorMessage);
            }
            
            Log::error('Failed to send via module', [
                'key_id' => $keyActivate->id,
                'user_tg_id' => $userTgId,
                'error' => $errorMessage
            ]);
            return NotificationResult::technicalError($errorMessage);
        }
    }

    /**
     * Отправка через бота продавца (старый метод для обратной совместимости)
     */
    private function sendViaSalesmanBot(KeyActivate $keyActivate, int $userTgId, string $message, array $keyboard = null): bool
    {
        $result = $this->sendViaSalesmanBotWithResult($keyActivate, $userTgId, $message, $keyboard);
        return $result->shouldCountAsSent;
    }

    /**
     * Отправка через бота продавца с детальным результатом
     */
    private function sendViaSalesmanBotWithResult(KeyActivate $keyActivate, int $userTgId, string $message, array $keyboard = null): NotificationResult
    {
        // Проверяем наличие packSalesman
        if (!$keyActivate->packSalesman) {
            Log::warning('No packSalesman found for notification', [
                'key_id' => $keyActivate->id,
                'pack_salesman_id' => $keyActivate->pack_salesman_id
            ]);
            return NotificationResult::technicalError('No packSalesman found');
        }

        $salesman = $keyActivate->packSalesman->salesman;
        if (!$salesman || !$salesman->token) {
            Log::warning('No salesman bot found for notification', [
                'key_id' => $keyActivate->id,
                'salesman_id' => $keyActivate->packSalesman->salesman_id
            ]);
            return NotificationResult::technicalError('No salesman bot found');
        }

        try {
            $telegram = new Api($salesman->token);

            $params = [
                'chat_id' => $userTgId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true
            ];

            if ($keyboard !== null) {
                $params['reply_markup'] = json_encode($keyboard);
            }

            $telegram->sendMessage($params);
            return NotificationResult::success();

        } catch (\Telegram\Bot\Exceptions\TelegramResponseException $e) {
            $errorMessage = $e->getMessage();
            
            // Обрабатываем специфичные ошибки Telegram API
            if ($this->isBlockedError($errorMessage)) {
                Log::warning('Bot was blocked by user or chat not found', [
                    'key_id' => $keyActivate->id,
                    'user_tg_id' => $userTgId,
                    'error' => $errorMessage
                ]);
                return NotificationResult::blocked($errorMessage);
            }

            Log::error('Failed to send via salesman bot (technical error)', [
                'key_id' => $keyActivate->id,
                'user_tg_id' => $userTgId,
                'error' => $errorMessage
            ]);
            return NotificationResult::technicalError($errorMessage);

        } catch (\Exception $e) {
            Log::error('Failed to send via salesman bot (exception)', [
                'key_id' => $keyActivate->id,
                'user_tg_id' => $userTgId,
                'error' => $e->getMessage()
            ]);
            return NotificationResult::technicalError($e->getMessage());
        }
    }

    /**
     * Проверка, является ли ошибка блокировкой бота
     */
    private function isBlockedError(string $errorMessage): bool
    {
        $blockedPatterns = [
            'chat not found',
            'Bad Request: chat not found',
            'bot was blocked',
            'user is deactivated',
            'chat_id is empty',
            'Forbidden: bot was blocked by the user',
            'Forbidden: user is deactivated',
        ];

        foreach ($blockedPatterns as $pattern) {
            if (stripos($errorMessage, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Отправка через FatherBot
     */
    private function sendViaFatherBot(int $chatId, string $message, array $keyboard = null): bool
    {
        try {
            $telegram = new Api(config('telegram.father_bot.token'));

            $params = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true
            ];

            if ($keyboard !== null) {
                $params['reply_markup'] = json_encode($keyboard);
            }

            $telegram->sendMessage($params);
            return true;

        } catch (\Telegram\Bot\Exceptions\TelegramResponseException $e) {
            $errorMessage = $e->getMessage();
            
            // Обрабатываем специфичные ошибки Telegram API
            if (strpos($errorMessage, 'chat not found') !== false || 
                strpos($errorMessage, 'Bad Request: chat not found') !== false) {
                Log::warning('Chat not found - user may have blocked bot or deleted chat', [
                    'chat_id' => $chatId,
                    'error' => $errorMessage
                ]);
                return false;
            }
            
            if (strpos($errorMessage, 'bot was blocked') !== false) {
                Log::warning('Bot was blocked by user', [
                    'chat_id' => $chatId,
                    'error' => $errorMessage
                ]);
                return false;
            }

            Log::error('Failed to send via FatherBot', [
                'chat_id' => $chatId,
                'error' => $errorMessage
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('Failed to send via FatherBot', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
