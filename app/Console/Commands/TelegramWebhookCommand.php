<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Telegram\Bot\Api;

/**
 * Консольная команда для управления Telegram webhook.
 *
 * Команда позволяет:
 * 1. Устанавливать webhook для указанного бота (father или salesman)
 * 2. Удалять webhook для указанного бота
 * 3. Работать с разными типами ботов и их токенами
 * 4. Обрабатывать ошибки при взаимодействии с Telegram API
 */
class TelegramWebhookCommand extends Command
{
    /**
     * Сигнатура команды для вызова из консоли.
     *
     * @var string
     */
    protected $signature = 'telegram:webhook {action=set} {--bot=father} {--token=}';
    /**
     * Описание команды, отображаемое при выводе списка команд.
     *
     * @var string
     */
    protected $description = 'Set or remove Telegram webhook. Use --bot=father|salesman to specify bot type';

    /**
     * Основной метод выполнения команды.
     *
     * @return void
     */
    public function handle()
    {
        $action = $this->argument('action');
        $botType = $this->option('bot');
        $token = $this->option('token');

        if (!in_array($botType, ['father', 'salesman'])) {
            $this->error('Invalid bot type. Use father or salesman');
            return;
        }

        if ($action === 'set') {
            $this->setWebhook($botType, $token);
        } elseif ($action === 'remove') {
            $this->removeWebhook($botType, $token);
        }
    }

    /**
     * Устанавливает webhook для указанного бота.
     *
     * @param string $botType Тип бота (father|salesman)
     * @param string|null $token Токен бота (опционально)
     * @return void
     */
    private function setWebhook(string $botType, ?string $token = null)
    {
        // Для основного бота берем токен из конфига, если не указан
        if ($botType === 'father') {
            $token = $token ?? config('telegram.father_bot.token');
        }

        if (empty($token)) {
            $this->error('Token is required for salesman bot');
            return;
        }

        $baseUrl = config('telegram.webhook_url', 'https://vpn-telegram.com');
        $url = "{$baseUrl}/{$botType}-bot/{$token}/init";

        try {
            $telegram = new Api($token);
            $response = $telegram->setWebhook([
                'url' => $url,
                'max_connections' => 100,
                'secret_token' => config('telegram.webhook_secret')
            ]);

            if ($response) {
                $this->info('Webhook was set');
                $this->info('Bot type: ' . $botType);
                $this->info('Webhook URL: ' . $url);
            } else {
                $this->error('Something went wrong');
            }
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * Удаляет webhook для указанного бота.
     *
     * @param string $botType Тип бота (father|salesman)
     * @param string|null $token Токен бота (опционально)
     * @return void
     */
    public function removeWebhook(string $botType, ?string $token = null)
    {
        if ($botType === 'father') {
            $token = $token ?? config('telegram.father_bot.token');
        }

        if (empty($token)) {
            $this->error('Token is required for salesman bot');
            return;
        }

        try {
            $telegram = new Api($token);
            $response = $telegram->removeWebhook();

            if ($response) {
                $this->info('Webhook was removed');
                $this->info('Bot type: ' . $botType);
            } else {
                $this->error('Something went wrong');
            }
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
