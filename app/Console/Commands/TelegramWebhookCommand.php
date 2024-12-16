<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook {action=set} {--bot=father} {--token=}';
    protected $description = 'Set or remove Telegram webhook. Use --bot=father|salesman to specify bot type';

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
            $telegram = new \Telegram\Bot\Api($token);
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

    private function removeWebhook(string $botType, ?string $token = null)
    {
        if ($botType === 'father') {
            $token = $token ?? config('telegram.father_bot.token');
        }

        if (empty($token)) {
            $this->error('Token is required for salesman bot');
            return;
        }

        try {
            $telegram = new \Telegram\Bot\Api($token);
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
