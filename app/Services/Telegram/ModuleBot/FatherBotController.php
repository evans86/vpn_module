<?php

namespace App\Services\Telegram\ModuleBot;

use App\Models\Pack\Pack;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Salesman\Salesman;
use Exception;
use Telegram\Bot\Api;
use Illuminate\Support\Facades\Log;

class FatherBotController extends AbstractTelegramBot
{
    private const STATE_WAITING_TOKEN = 'waiting_token';

    public function __construct(string $token)
    {
        parent::__construct($token);
        $this->setWebhook($token, self::BOT_TYPE_FATHER);
    }

    /**
     * Process incoming update and route to appropriate action
     */
    public function processUpdate(): void
    {
        try {
            $message = $this->update->getMessage();
            $callbackQuery = $this->update->getCallbackQuery();

            if ($callbackQuery) {
                Log::info('Received callback query', [
                    'data' => $callbackQuery->getData(),
                    'from' => $callbackQuery->getFrom()->getId()
                ]);
                $this->processCallback($callbackQuery->getData());
                return;
            }

            if ($message) {
                $text = $message->getText();

                if (!$text) {
                    Log::warning('Received message without text', [
                        'message' => $message
                    ]);
                    return;
                }

                if ($text === '/start') {
                    $this->start();
                    return;
                }

                // Проверяем состояние ожидания токена
                $salesman = Salesman::where('telegram_id', $this->chatId)->first();
                if ($salesman && $salesman->state === self::STATE_WAITING_TOKEN) {
                    $this->handleBotToken($text);
                    return;
                }

                // Обработка команд меню
                switch ($text) {
//                    case '📦 Купить пакет':
//                        $this->showPacksList();
//                        break;
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
                        $this->sendMessage('❌ Неизвестная команда. Воспользуйтесь меню для выбора действия.');
                }
            }
        } catch (Exception $e) {
            Log::error('Error processing update in FatherBot', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->sendErrorMessage();
        }
    }

    /**
     * Process callback queries
     */
    private function processCallback($data): void
    {
        try {
            Log::info('Processing callback data', ['data' => $data]);

            $params = json_decode($data, true);
            if (!$params || !isset($params['action'])) {
                Log::error('Invalid callback data', ['data' => $data]);
                return;
            }

            switch ($params['action']) {
                case 'change_bot':
                    $this->initiateBotChange();
                    break;
                default:
                    Log::warning('Unknown callback action', [
                        'action' => $params['action'],
                        'data' => $data
                    ]);
            }
        } catch (Exception $e) {
            Log::error('Process callback error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $this->sendErrorMessage();
        }
    }

    /**
     * Initiate bot change process
     */
    private function initiateBotChange(): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
            if (!$salesman) {
                $this->sendMessage("❌ Ошибка: продавец не найден");
                return;
            }

            $salesman->state = self::STATE_WAITING_TOKEN;
            $salesman->save();

            $this->sendMessage("<b>🔄 Введите токен нового бота:</b>\n\nТокен можно получить у @BotFather\n\n⚠️ Внимание: после смены бота все старые ссылки перестанут работать!");
        } catch (Exception $e) {
            Log::error('Initiate bot change error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

//    /**
//     * Process callback queries
//     */
//    private function processCallback($data): void
//    {
//        try {
//            Log::info('Processing callback data', ['data' => $data]);
//
//            $params = json_decode($data, true);
//            if (!$params || !isset($params['action'])) {
//                Log::error('Invalid callback data', ['data' => $data]);
//                return;
//            }
//
//            switch ($params['action']) {
//                case 'buy_pack':
//                    if (isset($params['pack_id'])) {
//                        $this->buyPack((int)$params['pack_id']);
//                    }
//                    break;
//                case 'confirm_purchase':
//                    if (isset($params['pack_id'])) {
//                        $this->confirmPurchase((int)$params['pack_id']);
//                    }
//                    break;
//                case 'check_payment':
//                    if (isset($params['payment_id'])) {
//                        $this->checkPayment((int)$params['payment_id']);
//                    }
//                    break;
//                default:
//                    Log::warning('Unknown callback action', [
//                        'action' => $params['action'],
//                        'data' => $data
//                    ]);
//            }
//        } catch (Exception $e) {
//            Log::error('Process callback error: ' . $e->getMessage(), [
//                'trace' => $e->getTraceAsString()
//            ]);
//            $this->sendErrorMessage();
//        }
//    }

//    /**
//     * Показать список пакетов
//     */
//    protected function showPacksList(): void
//    {
//        try {
//            $packs = Pack::all();
//            if ($packs->isEmpty()) {
//                $this->sendMessage('❌ В данный момент нет доступных пакетов');
//                return;
//            }
//
//            $message = "<b>📦 Доступные пакеты:</b>\n\n";
//            $inlineKeyboard = [];
//
//            foreach ($packs as $pack) {
//                $message .= "<b>{$pack->name}</b>\n";
//                $message .= "💰 Цена: {$pack->price} руб.\n";
//                $message .= "📝 Описание: {$pack->description}\n\n";
//
//                $inlineKeyboard[] = [
//                    [
//                        'text' => "Купить за {$pack->price} руб.",
//                        'callback_data' => json_encode([
//                            'action' => 'buy_pack',
//                            'pack_id' => $pack->id
//                        ])
//                    ]
//                ];
//            }
//
//            $keyboard = [
//                'inline_keyboard' => $inlineKeyboard
//            ];
//
//            $this->sendMessage($message, $keyboard);
//        } catch (Exception $e) {
//            Log::error('Show packs error: ' . $e->getMessage());
//            $this->sendErrorMessage();
//        }
//    }

//    /**
//     * Handle buy pack action
//     */
//    protected function buyPack(int $packId): void
//    {
//        try {
//            $pack = Pack::findOrFail($packId);
//            $salesman = Salesman::where('telegram_id', $this->chatId)->firstOrFail();
//
//            $message = "<b>💎 Подтверждение покупки пакета</b>\n\n";
//            $message .= "📦 Пакет: {$pack->name}\n";
//            $message .= "💰 Стоимость: {$pack->price} руб.\n\n";
//            $message .= "Для подтверждения покупки нажмите кнопку ниже:";
//
//            $keyboard = [
//                'inline_keyboard' => [
//                    [
//                        [
//                            'text' => "✅ Подтвердить покупку",
//                            'callback_data' => json_encode([
//                                'action' => 'confirm_purchase',
//                                'pack_id' => $pack->id
//                            ])
//                        ]
//                    ]
//                ]
//            ];
//
//            $this->sendMessage($message, $keyboard);
//        } catch (Exception $e) {
//            Log::error('Buy pack error: ' . $e->getMessage());
//            $this->sendErrorMessage();
//        }
//    }

//    /**
//     * Handle confirm purchase action
//     */
//    protected function confirmPurchase(int $packId): void
//    {
//        try {
//            $pack = Pack::findOrFail($packId);
//
//            $message = "💳 *Оплата пакета*\n\n";
//            $message .= "Сумма к оплате: {$pack->price} руб.\n\n";
//            $message .= "Для оплаты переведите указанную сумму по реквизитам:\n";
//            $message .= "💠 Сбербанк: `1234 5678 9012 3456`\n";
//            $message .= "💠 Тинькофф: `9876 5432 1098 7654`\n\n";
//            $message .= "❗️ В комментарии укажите: `VPN_{$this->chatId}`\n\n";
//            $message .= "После оплаты нажмите кнопку ниже:";
//
//            $keyboard = [
//                'inline_keyboard' => [
//                    [
//                        ['text' => "✅ Я оплатил", 'callback_data' => json_encode(['action' => 'check_payment', 'payment_id' => $packId])]
//                    ]
//                ]
//            ];
//
//            $this->sendMessage($message, ['reply_markup' => json_encode($keyboard)]);
//        } catch (\Exception $e) {
//            Log::error('Confirm purchase error: ' . $e->getMessage());
//            $this->sendErrorMessage();
//        }
//    }

//    /**
//     * Handle check payment action
//     */
//    protected function checkPayment(int $paymentId): void
//    {
//        try {
//            $pack = Pack::findOrFail($paymentId);
//            $salesman = Salesman::where('telegram_id', $this->chatId)->firstOrFail();
//
//            // Создаем пакет продавца
//            $packSalesman = new PackSalesman();
//            $packSalesman->pack_id = $pack->id;
//            $packSalesman->salesman_id = $salesman->id;
//            $packSalesman->status = PackSalesman::PAID;
//            $packSalesman->save();
//
//            // Создаем ключи для продавца
//            $keys = [];
//            $finish_at = time() + ($pack->period * 24 * 60 * 60); // период в днях переводим в секунды
//            $deleted_at = $finish_at + (7 * 24 * 60 * 60); // добавляем неделю для удаления
//
//            for ($i = 0; $i < $pack->count; $i++) {
//                $key = $this->keyActivateService->create(
//                    $pack->traffic_limit,
//                    $packSalesman->id,
//                    $finish_at,
//                    $deleted_at
//                );
//                $keys[] = $key;
//            }
//
//            $message = "✅ *Пакет успешно куплен!*\n\n";
//            $message .= "📦 Пакет: {$pack->name}\n";
//            $message .= "💰 Стоимость: {$pack->price} руб.\n\n";
//            $message .= "🔐 *Ваши ключи активации:*\n";
//            foreach ($keys as $index => $key) {
//                $message .= ($index + 1) . ". <code>{$key->id}</code>\n";
//            }
//            $message .= "\n❗️ Сохраните эти ключи - они понадобятся для активации VPN\n\n";
//
//            if (!$salesman->token) {
//                $message .= "❗️ *Важно:* Привяжите своего бота для начала продаж\n";
//                $message .= "Нажмите кнопку '🤖 Мой бот' в меню";
//            } else {
//                $message .= "🤖 Перейдите в своего бота для продажи ключей:\n";
//                $message .= $salesman->username;
//            }
//
//            $this->sendMessage($message);
//        } catch (\Exception $e) {
//            Log::error('Check payment error: ' . $e->getMessage());
//            $this->sendErrorMessage();
//        }
//    }

    /**
     * Handle bot token from user
     */
    private function handleBotToken(string $token): void
    {
        try {
            // Проверяем токен через Telegram API
            $telegram = new Api($token);
            $botInfo = $telegram->getMe();

            // Обновляем запись о продавце
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();

            if ($salesman) {
                $salesman->token = $token;
                $salesman->bot_link = 'https://t.me/' . $botInfo->username;
                $salesman->state = null; // Очищаем состояние
                $salesman->save();

                // Устанавливаем вебхук для бота продавца
                $salesmanBot = new Api($token);
                $webhookUrl = rtrim(self::WEBHOOK_BASE_URL, '/') . '/api/telegram/salesman-bot/' . $token . '/init';
                $salesmanBot->setWebhook(['url' => $webhookUrl]);

                $message = "✅ Бот успешно добавлен!\n\nТеперь вы можете купить пакет VPN-доступов.";
                $this->generateMenu($message);
//                $this->sendMessage("✅ Бот успешно добавлен!\n\nТеперь вы можете купить пакет VPN-доступов.");
            }
        } catch (\Exception $e) {
            Log::error('Bot token validation error: ' . $e->getMessage());
            $this->sendMessage("❌ Неверный токен бота. Пожалуйста, проверьте токен и попробуйте снова.");

            // Сбрасываем состояние при ошибке
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
            if ($salesman) {
                $salesman->state = null;
                $salesman->save();
            }
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

            $message = "👋 *Добро пожаловать в систему управления доступами VPN*\n\n";
            $message .= "🔸 Покупайте пакеты ключей\n";
            $message .= "🔸 Создавайте своего бота\n";
            $message .= "🔸 Продавайте VPN доступы";

            $this->generateMenu($message);
        } catch (\Exception $e) {
            Log::error('Start command error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Генерация меню
     */
    protected function generateMenu($message): void
    {
        $keyboard = [
            'keyboard' => [
                [
//                    ['text' => '📦 Купить пакет'],
                    ['text' => '🤖 Мой бот']
                ],
                [
                    ['text' => '👤 Профиль'],
                    ['text' => '❓ Помощь']
                ]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];

        $this->telegram->sendMessage([
            'chat_id' => $this->chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard)
        ]);
    }

    /**
     * @return void
     */
    protected function showHelp(): void
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
     * Показать информацию о боте
     */
    protected function showBotInfo(): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
            if (!$salesman) {
                $this->sendMessage("❌ Ошибка: продавец не найден");
                return;
            }

//            $salesman->token = null;
//            $salesman->save();
//            return;

            if (empty($salesman->token)) {
                $salesman->state = self::STATE_WAITING_TOKEN;
                $salesman->save();

                $this->sendMessage("<b>Введите токен вашего бота:</b>\n\nТокен можно получить у @BotFather");
                return;
            }

            $message = "<b>🤖 Информация о вашем боте</b>\n\n";
            $message .= "🔗 Ваш бот: $salesman->bot_link\n";
            $message .= "✅ Статус: Активен\n\n";

            // Добавляем инлайн-кнопку для привязки нового бота
            $keyboard = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => '🔄 Привязать нового бота',
                            'callback_data' => json_encode(['action' => 'change_bot'])
                        ]
                    ]
                ]
            ];

            $this->sendMessage($message, $keyboard);
        } catch (\Exception $e) {
            Log::error('Show bot info error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Показать профиль
     */
    protected function showProfile(): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->firstOrFail();
            $activePacks = PackSalesman::where('salesman_id', $salesman->id)
                ->where('status', PackSalesman::PAID)
                ->count();

            $message = "👤 *Ваш профиль*\n\n";
            $message .= "📦 Активных пакетов: {$activePacks}\n";

            $this->sendMessage($message);
        } catch (\Exception $e) {
            Log::error('Show profile error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }
}
