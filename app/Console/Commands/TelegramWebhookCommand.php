<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook {action=set}';
    protected $description = 'Set or remove Telegram webhook';

    public function handle()
    {
        $action = $this->argument('action');

        if ($action === 'set') {
            $this->setWebhook();
        } elseif ($action === 'remove') {
            $this->removeWebhook();
        }
    }

    private function setWebhook()
    {
        $token = config('telegram.father_bot.token');
        $url = config('telegram.father_bot.webhook_url') . '/' . $token . '/init';

        try {
            $response = Telegram::setWebhook([
                'url' => $url,
                'max_connections' => 100,
                'secret_token' => config('telegram.webhook_secret')
            ]);

            if ($response) {
                $this->info('Webhook was set');
                $this->info('Webhook URL: ' . $url);
            } else {
                $this->error('Something went wrong');
            }
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }

    private function removeWebhook()
    {
        try {
            $response = Telegram::removeWebhook();

            if ($response) {
                $this->info('Webhook was removed');
            } else {
                $this->error('Something went wrong');
            }
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
