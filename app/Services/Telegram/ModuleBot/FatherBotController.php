<?php

namespace App\Services\Telegram\ModuleBot;

use App\Dto\Salesman\SalesmanFactory;
use App\Models\Pack\Pack;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Salesman\Salesman;
use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;

class FatherBotController extends AbstractTelegramBot
{
    private const STATE_WAITING_TOKEN = 'waiting_token';
    private const STATE_WAITING_PAYMENT = 'waiting_payment';

    private ?string $userState = null;

    public function __construct(string $token)
    {
        parent::__construct($token);
        $this->setWebhook($token, self::BOT_TYPE_FATHER);
    }

    /**
     * Process incoming update and route to appropriate action
     */
    protected function processUpdate(): void
    {
        try {
            if ($this->update->getMessage()->text === '/start') {
                $this->userState = null;
                $this->start();
                return;
            }

            // Обработка callback'ов
            if ($this->update->callbackQuery) {
                $this->processCallback($this->update->callbackQuery->data);
                return;
            }

            $message = $this->update->getMessage();
            if (!$message) {
                return;
            }

            // Проверяем состояние ожидания токена
            if ($this->userState === self::STATE_WAITING_TOKEN) {
                $this->handleBotToken($message->text);
                return;
            }

            // Обработка команд меню
            switch ($message->text) {
                case '📦 Купить пакет':
                    $this->showPacksList();
                    break;
                case '🤖 Мой бот':
                    $this->showBotInfo();
                    break;
                case '👤 Профиль':
                    $this->showProfile();
                    break;
                case '❓ Помощь':
                    $this->showHelp();
                    break;
                default:
                    $this->sendMessage('❌ Неизвестная команда. Воспользуйтесь меню.');
                    $this->generateMenu();
            }
        } catch (\Exception $e) {
            Log::error('Process update error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Показать список пакетов
     */
    private function showPacksList(): void
    {
        try {
            $packs = Pack::where('status', true)->get();
            if ($packs->isEmpty()) {
                $this->sendMessage('❌ В данный момент нет доступных пакетов');
                return;
            }

            $message = "📦 *Доступные пакеты:*\n\n";
            $keyboard = new Keyboard();
            $keyboard->inline();

            foreach ($packs as $pack) {
                $message .= "🔸 *{$pack->name}*\n";
                $message .= "💰 Цена: {$pack->price} руб.\n";
                $message .= "📝 Описание: {$pack->description}\n\n";

                $keyboard->row(
                    ['text' => "Купить {$pack->name} за {$pack->price} руб.", 'callback_data' => "buy?id={$pack->id}"]
                );
            }

            $this->sendMessage($message, $keyboard->toJson());
        } catch (\Exception $e) {
            Log::error('Show packs error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Показать информацию о боте
     */
    private function showBotInfo(): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->firstOrFail();

            if (empty($salesman->token)) {
                $salesman->token = self::STATE_WAITING_TOKEN;
                $salesman->save();

                $this->userState = self::STATE_WAITING_TOKEN;
                $this->sendMessage('Отправьте токен вашего бота:');
                return;
            }

            $message = "🤖 *Информация о вашем боте*\n\n";
            $message .= "🔗 Ссылка на бота: {$salesman->bot_link}\n";
            $message .= "✅ Статус: Активен\n\n";
            $message .= "Чтобы привязать другого бота, отправьте новый токен.";

            $this->userState = self::STATE_WAITING_TOKEN;
            $this->sendMessage($message);
        } catch (\Exception $e) {
            Log::error('Show bot info error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Показать профиль
     */
    private function showProfile(): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->firstOrFail();
            $activePacks = PackSalesman::where('salesman_id', $salesman->id)
                ->where('status', PackSalesman::PAID)
                ->count();

            $message = "👤 *Ваш профиль*\n\n";
            if ($salesman->bot_link) {
                $message .= "🤖 Ваш бот: {$salesman->bot_link}\n";
            }
            $message .= "📦 Активных пакетов: {$activePacks}\n";

            $this->sendMessage($message);
        } catch (\Exception $e) {
            Log::error('Show profile error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Process callback queries
     */
    private function processCallback(string $data): void
    {
        try {
            $params = [];
            if (str_contains($data, '?')) {
                [$action, $queryString] = explode('?', $data);
                parse_str($queryString, $params);
            } else {
                $action = $data;
            }

            switch ($action) {
                case 'buy':
                    $this->handleBuyPack($params['id']);
                    break;
                case 'confirm':
                    $this->handleConfirmPurchase($params['id']);
                    break;
                case 'checkPayment':
                    $this->handleCheckPayment($params['id']);
                    break;
            }
        } catch (\Exception $e) {
            Log::error('Process callback error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Handle buy pack action
     */
    private function handleBuyPack(int $packId): void
    {
        try {
            $pack = Pack::findOrFail($packId);

            $message = "💎 *Подтверждение покупки пакета*\n\n";
            $message .= "📦 Пакет: {$pack->name}\n";
            $message .= "🔑 Количество ключей: {$pack->count}\n";
            $message .= "⏱ Срок действия: {$pack->period} дней\n";
            $message .= "💰 Стоимость: {$pack->price} руб.\n\n";
            $message .= "Для подтверждения покупки нажмите кнопку ниже:";

            $keyboard = [
                [
                    ['text' => "💳 Оплатить {$pack->price} руб.", 'callback_data' => "confirm?id={$packId}"]
                ]
            ];

            $this->sendMessage($message, ['reply_markup' => json_encode(['inline_keyboard' => $keyboard])]);
        } catch (\Exception $e) {
            Log::error('Buy pack error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Handle confirm purchase action
     */
    private function handleConfirmPurchase(int $packId): void
    {
        try {
            $pack = Pack::findOrFail($packId);

            $message = "💳 *Оплата пакета*\n\n";
            $message .= "Сумма к оплате: {$pack->price} руб.\n\n";
            $message .= "Для оплаты переведите указанную сумму по реквизитам:\n";
            $message .= "💠 Сбербанк: `1234 5678 9012 3456`\n";
            $message .= "💠 Тинькофф: `9876 5432 1098 7654`\n\n";
            $message .= "❗️ В комментарии укажите: `VPN_{$this->chatId}`\n\n";
            $message .= "После оплаты нажмите кнопку ниже:";

            $keyboard = [
                [
                    ['text' => "✅ Я оплатил", 'callback_data' => "checkPayment?id={$packId}"]
                ]
            ];

            $this->sendMessage($message, ['reply_markup' => json_encode(['inline_keyboard' => $keyboard])]);
        } catch (\Exception $e) {
            Log::error('Confirm purchase error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Handle check payment action
     */
    private function handleCheckPayment(int $packId): void
    {
        try {
            $pack = Pack::findOrFail($packId);
            $salesman = Salesman::where('telegram_id', $this->chatId)->firstOrFail();

            // Создаем пакет продавца
            $packSalesman = new PackSalesman();
            $packSalesman->pack_id = $pack->id;
            $packSalesman->salesman_id = $salesman->id;
            $packSalesman->status = PackSalesman::PAID; // Исправляем значение статуса на числовое (1) вместо строкового ('paid')
            $packSalesman->save();

            $message = "✅ *Пакет успешно куплен!*\n\n";
            $message .= "📦 Пакет: {$pack->name}\n";
            $message .= "🔑 Количество ключей: {$pack->count}\n";
            $message .= "⏱ Срок действия: {$pack->period} дней\n";
            $message .= "💰 Стоимость: {$pack->price} руб.\n\n";

            if (!$salesman->token) {
                $message .= "❗️ *Важно:* Привяжите своего бота для начала продаж\n";
                $message .= "Нажмите кнопку '🤖 Мой бот' в меню";
            } else {
                $message .= "🤖 Перейдите в своего бота для продажи ключей:\n";
                $message .= $salesman->bot_link;
            }

            $this->userState = null;
            $this->sendMessage($message);
            $this->generateMenu();
        } catch (\Exception $e) {
            Log::error('Check payment error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Handle bot token from user
     */
    private function handleBotToken(string $token): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->firstOrFail();

            // Устанавливаем webhook для бота продавца
            if (!$this->setWebhook($token, self::BOT_TYPE_SALESMAN)) {
                $this->sendMessage('❌ Ошибка при настройке бота. Пожалуйста, проверьте токен и попробуйте снова.');
                return;
            }

            // Проверяем валидность токена через Telegram API
            try {
                $telegram = new Api($token);
                $botInfo = $telegram->getMe();
                $botLink = '@' . $botInfo->username;
            } catch (\Exception $e) {
                Log::error('Invalid bot token: ' . $e->getMessage());
                $this->sendMessage('❌ Неверный токен бота. Пожалуйста, проверьте токен и попробуйте снова.');
                return;
            }

            // Обновляем данные продавца
            $salesman->token = $token;
            $salesman->bot_link = $botLink;
            $salesman->save();

            $this->userState = null;
            $this->sendMessage("✅ Бот успешно привязан!\nСсылка на бота: {$botLink}");
            $this->generateMenu();
        } catch (\Exception $e) {
            Log::error('Bot token handling error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Start command handler
     */
    protected function start(): void
    {
        try {
            // Проверяем существование пользователя
            $existingSalesman = Salesman::where('telegram_id', $this->chatId)->first();
//            Log::debug('existingSalesman: ' . $this->chatId);
//            Log::debug('existingSalesman: ' . $this->username);

            if (!$existingSalesman) {
                $this->salesmanService->create($this->chatId, $this->username == null ? null : $this->firstName);
            }

            $this->generateMenu();
        } catch (\Exception $e) {
            Log::error('Start command error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Генерация меню
     */
    protected function generateMenu(): void
    {
        $buttons = [
            ['text' => '📦 Купить пакет'],
            ['text' => '🤖 Мой бот'],
            ['text' => '👤 Профиль'],
            ['text' => '❓ Помощь']
        ];

        $keyboard = Keyboard::make()
            ->setResizeKeyboard(true)
            ->setOneTimeKeyboard(false);

        // Группируем кнопки по 2 в ряд
        $rows = array_chunk($buttons, 2);
        foreach ($rows as $row) {
            $keyboard->row(...$row);
        }

        $message = "👋 *Добро пожаловать в систему управления доступами VPN*\n\n";
        $message .= "🔸 Покупайте пакеты ключей\n";
        $message .= "🔸 Создавайте своего бота\n";
        $message .= "🔸 Продавайте VPN доступы\n";

        $this->sendMessage($message, $keyboard);
    }

    private function showHelp(): void
    {
        $message = "*❓ Помощь*\n\n";
        $message .= "🔹 *Покупка пакета:*\n";
        $message .= "1. Нажмите '📦 Купить пакет'\n";
        $message .= "2. Выберите подходящий пакет\n";
        $message .= "3. Оплатите его по указанным реквизитам\n\n";
        $message .= "🔹 *Создание бота:*\n";
        $message .= "1. Создайте бота у @BotFather\n";
        $message .= "2. Получите токен бота\n";
        $message .= "3. Нажмите '🤖 Мой бот' и отправьте токен\n\n";
        $message .= "🔹 *Продажа доступов:*\n";
        $message .= "1. Купите пакет ключей\n";
        $message .= "2. Привяжите своего бота\n";
        $message .= "3. Начните продавать доступы через своего бота\n\n";
        $message .= "По всем вопросам обращайтесь к @admin";

        $this->sendMessage($message);
    }
}
