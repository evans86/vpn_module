<?php

namespace App\Services\Telegram\ModuleBot;

use App\Dto\Salesman\SalesmanFactory;
use App\Models\Pack\Pack;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Salesman\Salesman;
use App\Services\Key\KeyActivateService;
use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;

class FatherBotController extends AbstractTelegramBot
{
    private const STATE_WAITING_TOKEN = 'waiting_token';
    private const STATE_WAITING_PAYMENT = 'waiting_payment';

    private ?string $userState = null;
    private KeyActivateService $keyActivateService;

    public function __construct(string $token)
    {
        parent::__construct($token);
        $this->setWebhook($token, self::BOT_TYPE_FATHER);
        $this->keyActivateService = app(KeyActivateService::class);
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
            $inlineKeyboard = [];

            foreach ($packs as $pack) {
                $message .= "🔸 *{$pack->name}*\n";
                $message .= "💰 Цена: {$pack->price} руб.\n";
                if ($pack->traffic_limit) {
                    $message .= "📊 Лимит трафика: " . $this->bytesToGB($pack->traffic_limit) . " GB\n";
                }
                $message .= "⏱ Срок действия: {$pack->period} дней\n";
                $message .= "📝 Описание: {$pack->description}\n\n";

                $inlineKeyboard[] = [
                    ['text' => "Купить {$pack->name} за {$pack->price} руб.", 'callback_data' => "buy?id={$pack->id}"]
                ];
            }

            $keyboard = new Keyboard([
                'inline_keyboard' => $inlineKeyboard
            ]);

            $this->sendMessage($message, $keyboard);
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
                $message = "🤖 *Привязка бота*\n\n";
                $message .= "Для начала продаж вам нужно привязать своего бота.\n\n";
                $message .= "Как создать бота:\n";
                $message .= "1. Перейдите к @BotFather\n";
                $message .= "2. Отправьте команду /newbot\n";
                $message .= "3. Следуйте инструкциям\n";
                $message .= "4. Скопируйте полученный токен\n";
                $message .= "5. Отправьте токен в этот чат\n\n";
                $message .= "❗️ Отправьте токен вашего бота:";

                $this->userState = self::STATE_WAITING_TOKEN;
                $this->sendMessage($message);
                return;
            }

            $message = "🤖 *Информация о вашем боте*\n\n";
            $message .= "🔗 Ссылка на бота: {$salesman->bot_link}\n";
            $message .= "✅ Статус: Активен\n\n";
            $message .= "Чтобы привязать другого бота, просто отправьте новый токен.";

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
                    if (isset($params['id'])) {
                        $this->handleBuyPack((int)$params['id']);
                    }
                    break;
                case 'confirm':
                    if (isset($params['id'])) {
                        $this->handleConfirmPurchase((int)$params['id']);
                    }
                    break;
                case 'checkPayment':
                    if (isset($params['id'])) {
                        $this->handleCheckPayment((int)$params['id']);
                    }
                    break;
                default:
                    Log::error('Unknown callback action: ' . $action);
                    $this->sendErrorMessage();
            }

            // Отвечаем на callback query, чтобы убрать загрузку с кнопки
            if ($this->update->callbackQuery) {
                $this->telegram->answerCallbackQuery([
                    'callback_query_id' => $this->update->callbackQuery->id
                ]);
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
            if ($pack->traffic_limit) {
                $message .= "📊 Лимит трафика: " . $this->bytesToGB($pack->traffic_limit) . " GB\n";
            }
            $message .= "⏱ Срок действия: {$pack->period} дней\n";
            $message .= "💰 Стоимость: {$pack->price} руб.\n\n";
            $message .= "Для подтверждения покупки нажмите кнопку ниже:";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => "💳 Оплатить {$pack->price} руб.", 'callback_data' => "confirm?id={$packId}"]
                    ]
                ]
            ];

            $this->sendMessage($message, ['reply_markup' => json_encode($keyboard)]);
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
                'inline_keyboard' => [
                    [
                        ['text' => "✅ Я оплатил", 'callback_data' => "checkPayment?id={$packId}"]
                    ]
                ]
            ];

            $this->sendMessage($message, ['reply_markup' => json_encode($keyboard)]);
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
            $packSalesman->status = PackSalesman::PAID;
            $packSalesman->save();

            // Создаем ключи для продавца
            $keys = [];
            $finish_at = time() + ($pack->period * 24 * 60 * 60); // период в днях переводим в секунды
            $deleted_at = $finish_at + (7 * 24 * 60 * 60); // добавляем неделю для удаления

            for ($i = 0; $i < $pack->count; $i++) {
                $key = $this->keyActivateService->create(
                    $pack->traffic_limit,
                    $packSalesman->id,
                    $finish_at,
                    $deleted_at
                );
                $keys[] = $key;
            }

            $message = "✅ *Пакет успешно куплен!*\n\n";
            $message .= "📦 Пакет: {$pack->name}\n";
            $message .= "🔑 Количество ключей: {$pack->count}\n";
            if ($pack->traffic_limit) {
                $message .= "📊 Лимит трафика: " . $this->bytesToGB($pack->traffic_limit) . " GB\n";
            }
            $message .= "⏱ Срок действия: {$pack->period} дней\n";
            $message .= "💰 Стоимость: {$pack->price} руб.\n\n";
            $message .= "🔐 *Ваши ключи активации:*\n";
            foreach ($keys as $index => $key) {
                $message .= ($index + 1) . ". `{$key->id}`\n";
            }
            $message .= "\n❗️ Сохраните эти ключи - они понадобятся для активации VPN\n\n";

            if (!$salesman->token) {
                $message .= "❗️ *Важно:* Привяжите своего бота для начала продаж\n";
                $message .= "Нажмите кнопку '🤖 Мой бот' в меню";
            } else {
                $message .= "🤖 Перейдите в своего бота для продажи ключей:\n";
                $message .= $salesman->bot_link;
            }

            $this->userState = null;
            $this->sendMessage($message);
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

            // Устанавливаем webhook для бота продавца
            if (!$this->setWebhook($token, self::BOT_TYPE_SALESMAN)) {
                $this->sendMessage('❌ Ошибка при настройке бота. Пожалуйста, проверьте токен и попробуйте снова.');
                return;
            }

            // Обновляем данные продавца
            $salesman->token = $token;
            $salesman->bot_link = $botLink;
            $salesman->save();

            $message = "✅ *Бот успешно привязан!*\n\n";
            $message .= "🔗 Ссылка на бота: {$botLink}\n";
            $message .= "✅ Статус: Активен\n\n";
            $message .= "Теперь вы можете продавать доступы через этого бота.";

            $this->userState = null;
            $this->sendMessage($message);
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

        $keyboard = new Keyboard([
            'keyboard' => array_chunk($buttons, 2),
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ]);

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

    /**
     * Конвертация байтов в гигабайты
     */
    private function bytesToGB(int $bytes): float
    {
        return round($bytes / (1024 * 1024 * 1024), 2);
    }
}
