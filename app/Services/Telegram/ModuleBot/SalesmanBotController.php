<?php

namespace App\Services\Telegram\ModuleBot;

use App\Models\KeyActivate\KeyActivate;
use App\Models\Salesman\Salesman;
use Telegram\Bot\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;

class SalesmanBotController extends AbstractTelegramBot
{
    private ?Salesman $salesman = null;
    private ?KeyActivate $currentPack = null;
    private const STATE_WAITING_KEY = 'waiting_key';
    private ?string $userState = null;

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

            $message = $this->update->getMessage();
            $callbackQuery = $this->update->getCallbackQuery();

            // Проверяем состояние ожидания ключа
            if ($this->userState === self::STATE_WAITING_KEY && $message) {
                $this->handleKeyActivation($message->getText());
                return;
            }

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
            ->row(
                Keyboard::inlineButton([
                    'text' => 'Активировать',
                    'callback_data' => 'activate',
                ]),
                Keyboard::inlineButton([
                    'text' => 'Статус',
                    'callback_data' => 'status',
                ]),
            )
            ->row(
                Keyboard::inlineButton([
                    'text' => 'Помощь',
                    'callback_data' => 'support',
                ])
            );

        $this->sendMessage('Выберите действие:', $keyboard);
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
            $userId = $this->update->getCallbackQuery()->getFrom()->getId();

            // Находим активные ключи через репозиторий
            $this->currentPack = $this->keyActivateRepository->findActiveKeyByUserAndSalesman(
                $userId,
                $this->salesman->id
            );

            if (!$this->currentPack) {
                $this->sendMessage("У вас нет активных VPN-доступов. Для приобретения обратитесь к менеджеру @{$this->getSalesmanUsername()}");
                return;
            }

            $text = "
                <b>Информация о вашем VPN-доступе:</b>\n
                ID доступа: {$this->currentPack->id}\n
                Статус: {$this->currentPack->getStatusText()}\n
                Дата покупки: {$this->currentPack->created_at->format('d.m.Y')}\n
                Действителен до: {$this->currentPack->finish_at->format('d.m.Y')}\n" .
                ($this->currentPack->traffic_limit ? "Остаток трафика: " . round($this->currentPack->traffic_limit / 1024 / 1024 / 1024, 2) . " GB" : "Безлимитный трафик");

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
            $userId = $this->update->getCallbackQuery()->getFrom()->getId();

            // Проверяем наличие активного ключа через репозиторий
            $existingPack = $this->keyActivateRepository->findActiveKeyByUserAndSalesman(
                $userId,
                $this->salesman->id
            );

            if ($existingPack) {
                $this->sendMessage("У вас уже есть активный VPN-доступ до {$existingPack->finish_at->format('d.m.Y')}.\nДля покупки дополнительного доступа обратитесь к менеджеру @{$this->getSalesmanUsername()}");
                return;
            }

            // Устанавливаем состояние ожидания ключа
            $this->userState = self::STATE_WAITING_KEY;
            $this->sendMessage("Пожалуйста, введите ключ активации, который вы получили от продавца:");

        } catch (\Exception $e) {
            Log::error('Activation error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Handle key activation
     */
    private function handleKeyActivation(string $keyId): void
    {
        try {
            $userId = $this->update->getMessage()->getFrom()->getId();

            // Находим ключ через репозиторий
            $key = $this->keyActivateRepository->findAvailableKeyForActivation(
                $keyId,
                $this->salesman->id
            );

            if (!$key) {
                $this->sendMessage("❌ Неверный ключ активации или ключ уже использован.\nПожалуйста, проверьте ключ и попробуйте снова, либо обратитесь к менеджеру @{$this->getSalesmanUsername()}");
                $this->userState = null;
                return;
            }

            // Активируем ключ для пользователя
            $this->keyActivateRepository->update($key, [
                'user_tg_id' => $userId,
                'status' => KeyActivate::ACTIVE
            ]);

            $this->currentPack = $key;
            $this->userState = null;

            $text = "
                <b>🎉 VPN-доступ успешно активирован!</b>\n
                ID доступа: {$this->currentPack->id}\n
                Действителен до: {$this->currentPack->finish_at->format('d.m.Y')}\n" .
                ($this->currentPack->traffic_limit ? "Доступный трафик: " . round($this->currentPack->traffic_limit / 1024 / 1024 / 1024, 2) . " GB" : "Безлимитный трафик") . "\n\n" .
                "Сейчас я отправлю вам инструкцию по настройке.";

            $this->sendMessage($text);
            $this->sendSetupInstructions();
        } catch (\Exception $e) {
            Log::error('Key activation error: ' . $e->getMessage());
            $this->sendErrorMessage();
            $this->userState = null;
        }
    }

    /**
     * VPN instructions
     */
    private function sendSetupInstructions(): void
    {
        // Формируем ссылку на конфигурацию VPN
        $configUrl = config('app.url') . '/config/' . $this->currentPack->key;

        $text = "<b>🔐 Ваш VPN успешно активирован!</b>\n\n";
        $text .= "📱 <b>Инструкция по настройке:</b>\n\n";
        $text .= "1. Откройте ссылку для загрузки конфигурации:\n";
        $text .= "<code>$configUrl</code>\n\n";

        // iOS
        $text .= "🍎 <b>iOS:</b>\n";
        $text .= "1. Установите приложение WireGuard из App Store\n";
        $text .= "2. Откройте ссылку выше\n";
        $text .= "3. Нажмите 'Добавить туннель'\n\n";

        // Android
        $text .= "🤖 <b>Android:</b>\n";
        $text .= "1. Установите приложение WireGuard из Google Play\n";
        $text .= "2. Откройте ссылку выше\n";
        $text .= "3. Разрешите добавление конфигурации\n\n";

        // Windows
        $text .= "💻 <b>Windows:</b>\n";
        $text .= "1. Установите WireGuard с официального сайта\n";
        $text .= "2. Откройте ссылку выше\n";
        $text .= "3. Импортируйте конфигурацию\n\n";

        $text .= "❓ Если возникли вопросы, обратитесь к менеджеру @{$this->getSalesmanUsername()}";

        $this->sendMessage($text);
    }

    /**
     * Salesman username
     * @return string
     */
    private function getSalesmanUsername(): string
    {
        if (!$this->salesman) {
            $this->salesman = $this->salesmanRepository->findByToken($this->telegram->getAccessToken());
        }
        return $this->salesman->username ?? 'support';
    }
}
