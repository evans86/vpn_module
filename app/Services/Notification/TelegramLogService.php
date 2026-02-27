<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Отправка логов миграции в Telegram: основной и резервный бот.
 * Сообщения уходят в оба бота (дублирование для надёжности).
 */
class TelegramLogService
{
    private const API = 'https://api.telegram.org/bot';

    public function send(string $text): void
    {
        $this->sendToBot('telegram_log.bot', $text);
        $this->sendToBot('telegram_log.bot_2', $text);
    }

    /**
     * Отправить только в основной бот; при ошибке — в резервный.
     */
    public function sendPrimaryWithFallback(string $text): void
    {
        if ($this->sendToBot('telegram_log.bot', $text)) {
            return;
        }
        $this->sendToBot('telegram_log.bot_2', $text);
    }

    private function sendToBot(string $configKey, string $text): bool
    {
        $token = config("{$configKey}.token");
        $chatId = config("{$configKey}.chat_id");
        if (empty($token) || empty($chatId)) {
            Log::debug('TelegramLog: bot not configured', ['key' => $configKey]);
            return false;
        }
        $url = self::API . $token . '/sendMessage';
        try {
            $response = Http::timeout(10)->post($url, [
                'chat_id' => $chatId,
                'text' => $text,
                'disable_web_page_preview' => true,
            ]);
            if (!$response->successful()) {
                Log::warning('TelegramLog: send failed', [
                    'key' => $configKey,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::warning('TelegramLog: exception', ['key' => $configKey, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
