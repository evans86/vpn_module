<?php

namespace App\Services\Telegram\ModuleBot;

use App\Dto\Salesman\SalesmanFactory;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Pack\Pack;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Salesman\Salesman;
use App\Services\Salesman\SalesmanService;
use App\Services\Telegram\TelegramKeyboard;
use Telegram\Bot\Keyboard\Button;
use Telegram\Bot\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;

class FatherBotController extends AbstractTelegramBot
{
    private const STATE_WAITING_TOKEN = 'waiting_token';
    private const STATE_WAITING_PAYMENT = 'waiting_payment';

    private ?string $userState = null;
    private ?int $pendingPackId = null;

    /**
     * Process incoming update and route to appropriate action
     */
    protected function processUpdate(): void
    {
        try {
            if ($this->update->getMessage()->text === '/start') {
                Log::debug('Send message: ' . $this->update->getMessage()->text);
                $this->userState = null;
                $this->start();
                return;
            }

            $message = $this->update->getMessage();
            $callbackQuery = $this->update->callbackQuery;

            // Проверяем состояние ожидания токена
            if ($this->userState === self::STATE_WAITING_TOKEN && $message) {
                $this->handleBotToken($message->text);
                return;
            }

            if ($this->userState === self::STATE_WAITING_PAYMENT && $callbackQuery) {
                $this->processCallback($callbackQuery->data);
                return;
            }

            if ($callbackQuery) {
                $this->processCallback($callbackQuery->data);
            }
        } catch (\Exception $e) {
            Log::error('Error processing update: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Handle bot token from user
     * @param string $token
     */
    private function handleBotToken(string $token): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->firstOrFail();

            // Устанавливаем webhook для бота продавца
            $webhookPath = 'salesman-bot/init';
            if (!$this->setWebhook($token, $webhookPath)) {
                $this->sendMessage('Ошибка при настройке бота. Пожалуйста, проверьте токен и попробуйте снова.');
                return;
            }

            $salesmanDto = SalesmanFactory::fromEntity($salesman);
            $salesmanDto->token = $token;
            $salesmanDto->bot_link = $this->getBotLinkFromToken($token);

            $this->salesmanService->updateToken($salesmanDto);

            $this->userState = null;
            $this->sendMessage("Бот успешно привязан!\nТокен: {$token}\nСсылка на бота: {$salesmanDto->bot_link}");
            $this->generateMenu();
        } catch (\Exception $e) {
            Log::error('Bot token handling error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Validate bot token format
     * @param string $token
     * @return bool
     */
//    private function isValidBotToken(string $token): bool
//    {
//        // Можно дополнить более сложной проверкой
//        return preg_match('/^\d+:[\w-]{35}$/', $token);
//    }

    /**
     * Get bot link from token
     * @param string $token
     * @return string
     */
    private function getBotLinkFromToken(string $token): string
    {
        // Получаем имя бота
        $botName = explode(':', $token)[0];
        return '@bot' . $botName;
    }

    /**
     * Start command handler
     */
    protected function start(): void
    {
        try {
            // Проверяем существование пользователя
            $existingSalesman = Salesman::where('telegram_id', $this->chatId)->first();
            Log::debug('existingSalesman: ' . $this->chatId);

            if (!$existingSalesman) {
                $this->salesmanService->create($this->chatId, $this->username);
            }

            $this->generateMenu();
        } catch (\Exception $e) {
            Log::error('Start command error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Generate menu
     */
    protected function generateMenu(): void
    {
        $keyboard = Keyboard::make()
            ->setResizeKeyboard(true)
            ->setOneTimeKeyboard(false)
            ->row(
                Keyboard::inlineButton([
                    'text' => '🛍 Купить пакет',
                    'callback_data' => 'packs'
                ]),
                Keyboard::inlineButton([
                    'text' => '🤖 Мой бот',
                    'callback_data' => 'bindBot'
                ])
            )
            ->row([
                Keyboard::inlineButton([
                    'text' => '👤 Профиль',
                    'callback_data' => 'profile'
                ]),
                Keyboard::inlineButton([
                    'text' => '❓ Помощь',
                    'callback_data' => 'help'
                ])
            ]);

        $message = "👋 *Добро пожаловать в систему управления доступами VPN*\n\n";
        $message .= "🔸 Покупайте пакеты ключей\n";
        $message .= "🔸 Создавайте своего бота\n";
        $message .= "🔸 Продавайте VPN доступы\n";

        Log::debug('Send message: ' . $message . json_encode($keyboard));

        $this->sendMessage($message, $keyboard);
    }

    /**
     * Process callback queries
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
     * Packs action
     */
    private function actionPacks(): void
    {
        $packs = Pack::all();
        $keyboard = new TelegramKeyboard();

        foreach ($packs as $pack) {
            $keyboard->addButtons([[
                "text" => "📦 {$pack->period} - {$pack->price}₽",
                "callback_data" => "pack?id={$pack->id}"
            ]]);
        }

        $message = "🛍 *Доступные пакеты ключей:*\n\n";
        $message .= "Выберите пакет для покупки:";

        $this->sendMessage($message, ['parse_mode' => 'Markdown', 'reply_markup' => $keyboard->getInline()]);
    }

    /**
     * Pack action
     */
    private function actionPack(int $id): void
    {
        /**
         * @var Pack $pack
         */
        $pack = Pack::find($id);
        if (!$pack) {
            $this->sendMessage('❌ Пакет не найден');
            return;
        }

        $keyboard = new TelegramKeyboard();
        $keyboard->addButtons([[
            "text" => "💳 Купить за {$pack->price}₽",
            "callback_data" => "confirmPurchase?id={$pack->id}"
        ]]);

        $message = "💎 *Характеристики пакета:*\n";
        $message .= "🔑 Количество ключей: {$pack->count}\n";
        $message .= "⏱ Срок действия: {$pack->period} дней\n";
        $message .= "📊 Трафик на ключ: {$pack->traffic_limit} GB\n";
        $message .= "💵 Стоимость: {$pack->price}₽\n\n";

        $this->sendMessage($message, ['parse_mode' => 'Markdown', 'reply_markup' => $keyboard->getInline()]);
    }

    /**
     * Confirm purchase action
     */
    private function actionConfirmPurchase(int $id): void
    {
        $pack = Pack::find($id);
        if (!$pack) {
            $this->sendMessage('❌ Пакет не найден');
            return;
        }

        $this->pendingPackId = $id;
        $this->userState = self::STATE_WAITING_PAYMENT;

        $message = "💳 *Оплата пакета {$pack->name}*\n\n";
        $message .= "Сумма к оплате: {$pack->price}₽\n\n";
        $message .= "Для оплаты переведите указанную сумму по реквизитам:\n";
//        $message .= "💠 Сбербанк: `1234 5678 9012 3456`\n";
//        $message .= "💠 Тинькофф: `9876 5432 1098 7654`\n\n";
//        $message .= "❗️ В комментарии укажите: `VPN_{$this->chatId}`\n\n";
        $message .= "После оплаты нажмите кнопку 'Я оплатил'";

        $keyboard = new TelegramKeyboard();
        $keyboard->addButtons([[
            "text" => "✅ Я оплатил",
            "callback_data" => "checkPayment?id={$id}"
        ]]);

        $this->sendMessage($message, ['parse_mode' => 'Markdown', 'reply_markup' => $keyboard->getInline()]);
    }

    /**
     * Check payment action
     */
    private function actionCheckPayment(int $id): void
    {
        if ($this->userState !== self::STATE_WAITING_PAYMENT || $this->pendingPackId !== $id) {
            $this->sendMessage('❌ Ошибка проверки оплаты. Начните покупку заново.');
            return;
        }

        try {
            /**
             * @var Pack $pack
             */
            $pack = Pack::find($id);
            if (!$pack) {
                $this->sendMessage('❌ Пакет не найден');
                return;
            }

            /**
             * @var Salesman $salesman
             */
            $salesman = Salesman::where('telegram_id', $this->chatId)->firstOrFail();

            // TODO: Проверка оплаты через платежную систему

            // Создаем пакет продавца
            $packSalesman = $this->packSalesmanService->create($pack->id, $salesman->id);
            // Создаем записи ключей активации
            $this->packSalesmanService->success($packSalesman->id);

            // Получаем все ключи пакета
            $keys = KeyActivate::where('pack_salesman_id', $packSalesman->id)
                ->where('status', KeyActivate::PAID)
                ->get();

            $this->userState = null;
            $this->pendingPackId = null;

            $message = "✅ *Пакет успешно куплен!*\n\n";
            $message .= "🔑 Количество ключей: {$pack->count}\n";
            $message .= "⏱ Срок действия: {$pack->period} дней\n";
            $message .= "📊 Трафик на ключи: {$pack->traffic_limit} GB\n\n";

            // Отправляем список ключей
            $message .= "*Ваши VPN ключи для продажи:*\n\n";
            foreach ($keys as $key) {
                $message .= "🔑 `{$key->id}`\n";
            }
            $message .= "\nℹ️ Эти ключи вы можете продавать через своего бота.\n";
            $message .= "Клиенты смогут активировать их через команду /activate\n\n";

            if (!$salesman->token) {
                $message .= "❗️ *Важно:* Привяжите своего бота для начала продаж\n";
                $message .= "Нажмите '🤖 Мой бот' в меню";
            } else {
                $message .= "🤖 Перейдите в своего бота для продажи ключей:\n";
                $message .= $salesman->bot_link;
            }

            $this->sendMessage($message, ['parse_mode' => 'Markdown']);
        } catch (\Exception $e) {
            Log::error('Pack purchase error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Profile action
     */
    private function actionProfile(): void
    {
        try {
            /**
             * @var Salesman $salesman
             */
            $salesman = Salesman::where('telegram_id', $this->chatId)->firstOrFail();

            $activePacks = PackSalesman::where('salesman_id', $salesman->id)
                ->where('status', PackSalesman::PAID)
                ->count();

            $totalKeys = KeyActivate::whereHas('packSalesman', function ($query) use ($salesman) {
                $query->where('salesman_id', $salesman->id);
            })->count();

            $soldKeys = KeyActivate::whereHas('packSalesman', function ($query) use ($salesman) {
                $query->where('salesman_id', $salesman->id);
            })
                ->whereNotNull('user_tg_id')
                ->count();

            $message = "👤 *Ваш профиль:*\n\n";
            $message .= "🆔 ID: `{$salesman->id}`\n";
            $message .= "👤 Username: @{$salesman->username}\n";
            $message .= "📅 Регистрация: {$salesman->created_at->format('d.m.Y')}\n\n";

            if ($salesman->token) {
                $message .= "🤖 *Ваш бот:*\n";
                $message .= "🔗 Ссылка: {$salesman->bot_link}\n\n";
            }

            $message .= "📊 *Статистика:*\n";
            $message .= "📦 Активных пакетов: {$activePacks}\n";
            $message .= "🔑 Всего ключей: {$totalKeys}\n";
            $message .= "✅ Продано ключей: {$soldKeys}\n";

            $this->sendMessage($message, ['parse_mode' => 'Markdown']);
        } catch (\Exception $e) {
            Log::error('Profile error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Help action
     */
    private function actionHelp(): void
    {
        $message = "❓ *Помощь по использованию бота*\n\n";
        $message .= "*Как начать продавать VPN:*\n\n";
        $message .= "1️⃣ Купите пакет ключей\n";
        $message .= "2️⃣ Создайте бота в @BotFather\n";
        $message .= "3️⃣ Привяжите полученный токен\n";
        $message .= "4️⃣ Начните продавать доступы\n\n";
        $message .= "*Дополнительно:*\n";
        $message .= "📦 Пакеты можно докупать\n";
        $message .= "🔄 Ключи активируются автоматически\n";
        $message .= "📊 Статистика доступна в профиле\n\n";
        $message .= "Остались вопросы? Пишите @support";

        $this->sendMessage($message, ['parse_mode' => 'Markdown']);
    }

    /**
     * Bind bot action handler
     */
    private function actionBindBot(): void
    {
        try {
            /**
             * @var Salesman $salesman
             */
            $salesman = Salesman::where('telegram_id', $this->chatId)->firstOrFail();

            if ($salesman->token) {
                $text = "У вас уже привязан бот:\nТокен: {$salesman->token}\nСсылка: {$salesman->bot_link}\n\n";
                $text .= "Хотите привязать другого бота? Отправьте новый токен.";
            } else {
                $text = "Пожалуйста, отправьте токен вашего бота.\n\n";
                $text .= "Токен можно получить у @BotFather после создания нового бота.";
            }

            $this->userState = self::STATE_WAITING_TOKEN;
            $this->sendMessage($text);
        } catch (\Exception $e) {
            Log::error('Bot binding error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }
}
