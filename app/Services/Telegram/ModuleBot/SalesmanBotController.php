<?php

namespace App\Services\Telegram\ModuleBot;

use App\Models\KeyActivate\KeyActivate;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Salesman\Salesman;
use Telegram\Bot\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;

class SalesmanBotController extends AbstractTelegramBot
{
    private ?Salesman $salesman = null;
    private ?KeyActivate $currentPack = null;

    /**
     * обработка update
     */
    protected function processUpdate(): void
    {
        try {
            if ($this->update->getMessage()->getText() === '/start') {
                $this->start();
                return;
            }

            $callbackQuery = $this->update->getCallbackQuery();

            if ($callbackQuery) {
                $this->processCallback($callbackQuery->getData());
            }
        } catch (\Exception $e) {
            Log::error('Error processing update: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * обработка callback
     *
     * @param string $data
     */
    private function processCallback(string $data): void
    {
        $params = [];
        if (str_contains($data, '?')) {
            [$action, $queryString] = explode('?', $data);
            parse_str($queryString, $params);
        } else {
            $action = $data;
        }

        $methodName = 'action' . ucfirst($action);
        if (method_exists($this, $methodName)) {
            $this->$methodName($params['id'] ?? null);
        }
    }

    /**
     * обработка start
     */
    protected function start(): void
    {
        try {
            $this->generateMenu();
        } catch (\Exception $e) {
            Log::error('Start command error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Меню бота
     */
    protected function generateMenu(): void
    {
        $keyboard = Keyboard::make()
            ->setResizeKeyboard(true)
            ->setOneTimeKeyboard(true)
            ->row([
                Keyboard::inlineButton([
                    'text' => 'Помощь',
                    'callback_data' => 'support',
                ])
            ])
            ->row([
                Keyboard::inlineButton([
                    'text' => 'Статус',
                    'callback_data' => 'status',
                ]),
                Keyboard::inlineButton([
                    'text' => 'Активировать доступ',
                    'callback_data' => 'activate',
                ]),
            ]);

        $this->sendMessage('Добро пожаловать в бот для управления VPN доступом', $keyboard);
    }

    /**
     * Support action
     */
    private function actionSupport(): void
    {
        $text = "
            <b>Как использовать VPN:</b>\n
            1. Активируйте доступ через меню\n
            2. Следуйте инструкциям для настройки\n
            3. Проверьте статус подключения\n
            \nПо всем вопросам обращайтесь к менеджеру @{$this->getSalesmanUsername()}
        ";
        $this->sendMessage($text);
    }

    /**
     * Status action
     */
    private function actionStatus(): void
    {
        try {
            // TODO: Здесь наличие доступа у пользователя и его статус $this->currentPack = keyActivate

            $text = "
                <b>Информация о вашем пакете:</b>\n
                ID пакета: {$this->currentPack->id}\n
                Статус: " . ($this->currentPack->status === PackSalesman::PAID ? 'активен' : 'неактивен') . "\n
                Дата покупки: {$this->currentPack->created_at->format('d.m.Y')}
            ";

            $this->sendMessage($text);
        } catch (\Exception $e) {
            Log::error('Pack info error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Activate action
     */
    private function actionActivate(): void
    {
        try {
            // TODO: Здесь будет вызов доступа $this->currentPack = keyActivate

            $text = "
                <b>Доступ успешно создан!</b>\n
                Срок действия: {$this->currentPack->finish_at}\n
            ";

            $this->sendMessage($text);
            $this->sendSetupInstructions();
        } catch (\Exception $e) {
            Log::error('Confirm sale error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * VPN instructions
     */
    private function sendSetupInstructions(): void
    {
        $text = "<b>📱 Инструкция по настройке VPN:</b>\n\n";

        // Android
        $text .= "🤖 <b>Android:</b>\n";
        $text .= "1. Установите приложение ...\n";
        $text .= "2. Откройте файл конфигурации\n";
        $text .= "3. Нажмите 'Import'\n\n";

        // iOS
        $text .= "🍎 <b>iOS:</b>\n";
        $text .= "1. Установите приложение ...\n";
        $text .= "2. Откройте файл конфигурации\n";
        $text .= "3. Нажмите 'Add'\n\n";

        // Windows
        $text .= "💻 <b>Windows:</b>\n";
        $text .= "1. Установите ...\n";

        $text .= "❓ Если возникли вопросы, обратитесь в поддержку";

        $this->sendMessage($text);
    }

    /**
     * Salesman username
     * @return string
     */
    private function getSalesmanUsername(): string
    {
        if (!$this->salesman) {
            $this->salesman = Salesman::where('token', $this->telegram->getAccessToken())->first();
        }
        return $this->salesman->username ?? 'support';
    }
}
