<?php

namespace App\Services\Notification;

use App\Services\External\BottApi;
use App\Dto\Bot\BotModuleFactory;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Salesman\Salesman;
use Telegram\Bot\Api;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    /**
     * Отправка сообщения пользователю через правильный канал
     */
    public function sendToUser(KeyActivate $keyActivate, string $message, array $keyboard = null): bool
    {
        try {
            $userTgId = $keyActivate->user_tg_id;

            if (!$userTgId) {
                Log::warning('Cannot send notification: user not found', [
                    'key_id' => $keyActivate->id
                ]);
                return false;
            }

            // Определяем способ отправки (модуль или обычный бот)
            if (!is_null($keyActivate->module_salesman_id)) {
                // Отправка через модуль
                return $this->sendViaModule($keyActivate, $userTgId, $message, $keyboard);
            } else {
                // Отправка через обычного бота продавца
                return $this->sendViaSalesmanBot($keyActivate, $userTgId, $message, $keyboard);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send notification to user', [
                'key_id' => $keyActivate->id,
                'user_tg_id' => $keyActivate->user_tg_id,
                'error' => $e->getMessage()
            ]);
            return false;
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
     * Отправка через модуль бота
     */
    private function sendViaModule(KeyActivate $keyActivate, int $userTgId, string $message, array $keyboard = null): bool
    {
        $salesman = $keyActivate->moduleSalesman;
        if (!$salesman || !$salesman->botModule) {
            Log::warning('No module found for notification', [
                'key_id' => $keyActivate->id,
                'salesman_id' => $keyActivate->module_salesman_id
            ]);
            return false;
        }

        try {
            $result = BottApi::senModuleMessage(
                BotModuleFactory::fromEntity($salesman->botModule),
                $userTgId,
                $message
            );

            // Проверяем результат отправки
            // Предполагаем, что BottApi::senModuleMessage возвращает массив с ключом 'result' или 'success'
            if (isset($result['result']) && $result['result'] === true) {
                return true;
            }

            if (isset($result['success']) && $result['success'] === true) {
                return true;
            }

            // Если структура ответа другая, логируем для отладки
            Log::warning('Unexpected response format from BottApi::senModuleMessage', [
                'key_id' => $keyActivate->id,
                'response' => $result
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to send via module', [
                'key_id' => $keyActivate->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Отправка через бота продавца
     */
    private function sendViaSalesmanBot(KeyActivate $keyActivate, int $userTgId, string $message, array $keyboard = null): bool
    {
        $salesman = $keyActivate->packSalesman->salesman;
        if (!$salesman || !$salesman->token) {
            Log::warning('No salesman bot found for notification', [
                'key_id' => $keyActivate->id,
                'salesman_id' => $keyActivate->packSalesman->salesman_id
            ]);
            return false;
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
            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send via salesman bot', [
                'key_id' => $keyActivate->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
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

        } catch (\Exception $e) {
            Log::error('Failed to send via FatherBot', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
