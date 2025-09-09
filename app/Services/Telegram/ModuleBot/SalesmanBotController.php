<?php

namespace App\Services\Telegram\ModuleBot;

use App\Models\KeyActivate\KeyActivate;
use App\Models\Salesman\Salesman;
use App\Models\TelegramUser\TelegramUser;
use App\Services\Panel\PanelStrategy;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SalesmanBotController extends AbstractTelegramBot
{
    private ?Salesman $salesman;
    private array $userPages = [];

    public function __construct(string $token)
    {
        parent::__construct($token);

        // Находим продавца по токену
        $this->salesman = $this->salesmanRepository->findByToken($token);
        if (!$this->salesman) {
            Log::error('Salesman not found for token: ' . substr($token, 0, 10) . '...');
            throw new RuntimeException('Salesman not found');
        }

        Log::info('Initialized SalesmanBotController', [
            'salesman_id' => $this->salesman->id,
            'token' => substr($token, 0, 10) . '...'
        ]);
    }

    /**
     * обработка update
     */
    public function processUpdate(): void
    {
        try {
            // Проверяем, активен ли бот
            if (!$this->salesman->bot_active) {
                $this->sendMessage("⚠️ Бот временно отключен администратором.");
                return;
            }

            $message = $this->update->getMessage();
            $callbackQuery = $this->update->getCallbackQuery();

            if ($callbackQuery) {
                $messageId = $callbackQuery->getMessage()->getMessageId();
                $data = $callbackQuery->getData();

                // Обработка callback для пагинации активных подписок
                if (strpos($data, 'status_page_') === 0) {
                    $page = (int)str_replace('status_page_', '', $data);
                    $this->actionStatus($page, $messageId);
                    return;
                }

                // Обработка callback для пагинации неактивных подписок
                if (strpos($data, 'inactive_page_') === 0) {
                    $page = (int)str_replace('inactive_page_', '', $data);
                    $this->actionInactiveSubscriptions($page, $messageId);
                    return;
                }

                // Обработка callback для деталей подписки
                if (strpos($data, 'subscription_details_') === 0) {
                    $keyId = str_replace('subscription_details_', '', $data);
                    $this->actionStatus(0, $messageId, $keyId);
                    return;
                }

                // Обработка callback для просмотра неактивных подписок
                if ($data === 'inactive_subscriptions') {
                    $this->actionInactiveSubscriptions(0, $messageId);
                    return;
                }

                // Обработка callback для просмотра неактивных подписок
                if ($data === 'activate_key') {
                    $this->actionActivate();
                    return;
                }
            }

            if (!$message || !$message->getText()) {
                return;
            }

            $text = $message->getText();

            if ($text === '/start') {
                $this->start();
                return;
            }

            // Обработка команд меню
            switch ($text) {
                case '🔑 Активировать':
                    $this->actionActivate();
                    break;
                case '📊 Статус':
                    $this->actionStatus();
                    break;
                case '❓ Помощь':
                    $this->actionHelp();
                    break;
                default:
                    // Проверяем, похож ли текст на ключ
                    if ($this->isValidKeyFormat($text)) {
                        $this->handleKeyActivation($text);
                    } else {
                        $this->sendMessage('❌ Неизвестная команда. Воспользуйтесь меню для выбора действия.');
                    }
            }
        } catch (\Exception $e) {
            Log::error('Process update error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    protected function ensureTelegramUserExists(): void
    {
        try {
            // Получаем данные пользователя из Telegram
            $message = $this->update->getMessage();
            $from = $message->getFrom();

            $telegramId = $from->getId();
            $username = $from->getUsername();
            $firstName = $from->getFirstName();

            // Проверяем, существует ли пользователь в таблице
            $existingUser = TelegramUser::where('telegram_id', $telegramId)
                ->where('salesman_id', $this->salesman->id)
                ->first();

            if (!$existingUser) {
                // Создаем нового пользователя
                TelegramUser::create([
                    'salesman_id' => $this->salesman->id,
                    'telegram_id' => $telegramId,
                    'username' => $username,
                    'first_name' => $firstName,
                    'status' => 1, // пока статус "активен"
                ]);

                Log::info('New Telegram user added', [
                    'telegram_id' => $telegramId,
                    'username' => $username,
                    'salesman_id' => $this->salesman->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to ensure Telegram user exists: ' . $e->getMessage());
        }
    }

    protected function start(): void
    {
        try {
            $this->ensureTelegramUserExists();

            $message = "👋 Добро пожаловать в VPN бот!\n\n";
            $message .= "🔸 Активируйте ваш VPN доступ\n";
            $message .= "🔸 Проверяйте статус подключения\n";
            $message .= "🔸 Получайте помощь в настройке";

            $this->generateMenu($message);
        } catch (\Exception $e) {
            Log::error('Start command error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    protected function generateMenu($message): void
    {
        $keyboard = [
            'keyboard' => [
                [
                    ['text' => '🔑 Активировать']
                ],
                [
                    ['text' => '📊 Статус'],
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

    protected function actionActivate(): void
    {
        try {
            // Проверяем, есть ли у пользователя уже активный ключ через репозиторий
            $existingKey = $this->keyActivateRepository->findActiveKeyByUserAndSalesman(
                $this->chatId,
                $this->salesman->id
            );

            if ($existingKey) {
                $finishDate = date('d.m.Y', $existingKey->finish_at);
                $this->sendMessage("У вас уже есть активный VPN-доступ до {$finishDate}.\n\nДля покупки дополнительного доступа обратитесь к @admin");
                return;
            }

            $this->sendMessage("Пожалуйста, отправьте ваш ключ активации:");
        } catch (\Exception $e) {
            Log::error('Activate action error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    protected function actionStatus(int $page = 0, ?int $messageId = null, ?string $keyId = null): void
    {
        try {
            // Если передан keyId, отображаем детали подписки
            if ($keyId !== null) {
                $this->showSubscriptionDetails($keyId, $messageId);
                return;
            }

            $chatId = $this->chatId;
            $this->setCurrentPage($chatId, $page);

            /**
             * @var KeyActivate[] $activeKeys
             */
            $activeKeys = $this->keyActivateRepository->findAllActiveKeysByUser(
                $this->chatId,
                $this->salesman->id,
                KeyActivate::ACTIVE
            );

            if ($activeKeys->isEmpty()) {
                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => '🔑 Активировать', 'callback_data' => 'activate_key']],
                        [['text' => '📋 Просмотр неактивных', 'callback_data' => 'inactive_subscriptions']]
                    ]
                ];
                $this->sendMessage("Упс…\n
Кажется, что у вас <code>нет активный ключей</code>, но после покупки – они обязательно здесь будут Вас ждать! ", $keyboard);
                return;
            }

            // Разбиваем на страницы
            $perPage = 5;
            $totalPages = ceil($activeKeys->count() / $perPage);
            $currentPageKeys = $activeKeys->slice($page * $perPage, $perPage);

            $message = "<blockquote><b>📊 Ваши VPN-подписки:</b></blockquote>\n\n\n";

            /**
             * @var KeyActivate $key
             */
            foreach ($currentPageKeys as $key) {
                try {
                    // Получаем информацию о трафике с панели
                    $panelStrategy = new PanelStrategy($key->keyActivateUser->serverUser->panel->panel);
                    $info = $panelStrategy->getSubscribeInfo($key->keyActivateUser->serverUser->panel->id, $key->keyActivateUser->serverUser->id);
                } catch (\Exception $e) {
                    // Логируем ошибку
                    Log::error('Failed to get subscription info for key ' . $key->id . ': ' . $e->getMessage());
                    $info = ['used_traffic' => null];
                }

                $finishDate = date('d.m.Y', $key->finish_at);
                $daysRemaining = ceil(($key->finish_at - time()) / (60 * 60 * 24)); // Оставшиеся дни

                $message .= "🔑 *Подписка <code>{$key->id}</code>*\n";
                $message .= "📅 Действует до: {$finishDate}\n";
                $message .= "⏳ Осталось: {$daysRemaining} дней\n";

                if ($key->traffic_limit) {
                    $trafficGB = round($key->traffic_limit / (1024 * 1024 * 1024), 2);
                    $message .= "📊 Лимит трафика: {$trafficGB} GB\n";
                }

                if ($info['used_traffic']) {
                    $trafficUsedGB = round($info['used_traffic'] / (1024 * 1024 * 1024), 2);
                    $message .= "📊 Использовано: {$trafficUsedGB} GB\n";
                }

                $message .= "🔗 [Открыть конфигурацию](https://vpn-telegram.com/config/{$key->id})\n\n";
            }

            $message .= "Страница " . ($page + 1) . " из $totalPages";

            // Добавляем кнопки пагинации
            $keyboard = [
                'inline_keyboard' => []
            ];

            // Кнопки для каждой подписки
            foreach ($currentPageKeys as $key) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => '🔍 Подробнее о подписке ' . $key->id, 'callback_data' => 'subscription_details_' . $key->id]
                ];
            }

            // Кнопки пагинации
            $paginationButtons = [];

            if ($page > 0) {
                $paginationButtons[] = ['text' => '⬅️ Назад', 'callback_data' => 'status_page_' . ($page - 1)];
                $paginationButtons[] = ['text' => 'В начало', 'callback_data' => 'status_page_0'];
            }

            if ($page < $totalPages - 1) {
                $paginationButtons[] = ['text' => 'Вперед ➡️', 'callback_data' => 'status_page_' . ($page + 1)];
            }

            // Кнопка "Просмотр неактивных"
            $keyboard['inline_keyboard'][] = [
                ['text' => '📋 Просмотр неактивных', 'callback_data' => 'inactive_subscriptions']
            ];

            if (!empty($paginationButtons)) {
                $keyboard['inline_keyboard'][] = $paginationButtons;
            }

            if ($messageId) {
                $this->editMessage($message, $keyboard, $messageId);
            } else {
                $this->sendMessage($message, $keyboard);
            }

        } catch (\Exception $e) {
            Log::error('Status action error: ' . $e->getMessage() . ' | User ID: ' . $this->chatId . ' | Page: ' . $page);
            $this->sendErrorMessage();
        }
    }

    protected function actionInactiveSubscriptions(int $page = 0, ?int $messageId = null): void
    {
        try {
            $chatId = $this->chatId;
            $this->setCurrentPage($chatId, $page);

            /**
             * @var KeyActivate[] $inactiveKeys
             */
            $inactiveKeys = $this->keyActivateRepository->findAllActiveKeysByUser(
                $this->chatId,
                $this->salesman->id,
                KeyActivate::EXPIRED
            );

            if ($inactiveKeys->isEmpty()) {
                $this->sendMessage("У вас нет неактивных ключей.");
                return;
            }

            // Разбиваем на страницы
            $perPage = 5;
            $totalPages = ceil($inactiveKeys->count() / $perPage);
            $currentPageKeys = $inactiveKeys->slice($page * $perPage, $perPage);

            $message = "<blockquote><b>📋 Неактивные VPN-подписки:</b></blockquote>\n\n\n";

            foreach ($currentPageKeys as $key) {
                $finishDate = date('d.m.Y', $key->finish_at);
                $daysRemaining = ceil(($key->finish_at - time()) / (60 * 60 * 24)); // Оставшиеся дни

                $message .= "🔑 *Подписка <code>{$key->id}</code>*\n";
                $message .= "📅 Действует до: {$finishDate}\n";
                $message .= "⏳ Осталось: {$daysRemaining} дней\n";

                if ($key->traffic_limit) {
                    $trafficGB = round($key->traffic_limit / (1024 * 1024 * 1024), 2);
                    $message .= "📊 Лимит трафика: {$trafficGB} GB\n";
                }

                $message .= "🔗 [Открыть конфигурацию](https://vpn-telegram.com/config/{$key->id})\n\n";
            }

            $message .= "Страница " . ($page + 1) . " из $totalPages";

            // Добавляем кнопки пагинации
            $keyboard = [
                'inline_keyboard' => []
            ];

            // Кнопки пагинации
            $paginationButtons = [];

            if ($page > 0) {
                $paginationButtons[] = ['text' => '⬅️ Назад', 'callback_data' => 'inactive_page_' . ($page - 1)];
                $paginationButtons[] = ['text' => 'В начало', 'callback_data' => 'inactive_page_0'];
            }

            if ($page < $totalPages - 1) {
                $paginationButtons[] = ['text' => 'Вперед ➡️', 'callback_data' => 'inactive_page_' . ($page + 1)];
            }

            // Кнопка "Назад к активным"
            $keyboard['inline_keyboard'][] = [
                ['text' => '⬅️ Назад к активным', 'callback_data' => 'status_page_0']
            ];

            if (!empty($paginationButtons)) {
                $keyboard['inline_keyboard'][] = $paginationButtons;
            }

            if ($messageId) {
                $this->editMessage($message, $keyboard, $messageId);
            } else {
                $this->sendMessage($message, $keyboard);
            }

        } catch (\Exception $e) {
            Log::error('Inactive subscriptions action error: ' . $e->getMessage() . ' | User ID: ' . $this->chatId . ' | Page: ' . $page);
            $this->sendErrorMessage();
        }
    }

    protected function showSubscriptionDetails(string $keyId, ?int $messageId = null): void
    {
        try {
            /**
             * @var KeyActivate $key
             */
            $key = $this->keyActivateRepository->findById($keyId);

            if (!$key) {
                $this->sendMessage("Подписка не найдена.");
                return;
            }

            $finishDate = date('d.m.Y', $key->finish_at);
            $daysRemaining = ceil(($key->finish_at - time()) / (60 * 60 * 24)); // Оставшиеся дни

            $message = "🔑 *Подписка <code>{$key->id}</code>*\n";
            $message .= "📅 Действует до: {$finishDate}\n";
            $message .= "⏳ Осталось: {$daysRemaining} дней\n";

            if ($key->traffic_limit) {
                $trafficGB = round($key->traffic_limit / (1024 * 1024 * 1024), 2);
                $message .= "📊 Лимит трафика: {$trafficGB} GB\n";
            }

            if ($key->traffic_used) {
                $trafficUsedGB = round($key->traffic_used / (1024 * 1024 * 1024), 2);
                $message .= "📊 Использовано: {$trafficUsedGB} GB\n";
            }

            $message .= "🔗 [Открыть конфигурацию](https://vpn-telegram.com/config/{$key->id})\n\n";

            // Кнопка "Назад к списку подписок"
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '⬅️ Назад к списку подписок', 'callback_data' => 'status_page_0']]
                ]
            ];

            if ($messageId) {
                $this->editMessage($message, $keyboard, $messageId);
            } else {
                $this->sendMessage($message, $keyboard);
            }

        } catch (\Exception $e) {
            Log::error('Subscription details error: ' . $e->getMessage() . ' | User ID: ' . $this->chatId . ' | Key ID: ' . $keyId);
            $this->sendErrorMessage();
        }
    }

    protected function getCurrentPage(int $chatId): int
    {
        return $this->userPages[$chatId] ?? 0;
    }

    protected function setCurrentPage(int $chatId, int $page): void
    {
        $this->userPages[$chatId] = $page;
    }

    protected function actionHelp(): void
    {
        // Если есть кастомный текст помощи, используем его
        if (!empty($this->salesman->custom_help_text)) {
            $this->sendMessage($this->salesman->custom_help_text);
            return;
        }

        // Стандартный текст помощи
        $text = "<blockquote><b>❓ Помощь</b></blockquote>\n\n\n";
        $text .= "🔹 <b>Активация VPN:</b>\n\n";
        $text .= "1️⃣ Нажмите '🔑 Активировать'\n";
        $text .= "2️⃣ Введите полученный ключ\n";
        $text .= "3️⃣ Скопируйте конфигурацию и следуйте инструкциям для подключения на различных устройствах, представленным ниже\n\n";
        $text .= "🔹 <b>Проверка статуса:</b>\n\n";
        $text .= "1️⃣ Нажмите кнопку '📊 Статус'\n";
        $text .= "2️⃣ Просмотрите информацию о вашем доступе и конфигурации\n\n";
        $text .= "📁 <b>Инструкции по настройке VPN:</b>\n\n";
        $text .= "- <a href=\"https://teletype.in/@bott_manager/C0WFg-Bsren\">Инструкция для Android</a> 🤖\n";
        $text .= "- <a href=\"https://teletype.in/@bott_manager/8jEexiKqjlEWQ\">Инструкция для IOS</a> 🍏\n";
        $text .= "- <a href=\"https://teletype.in/@bott_manager/kJaChoXUqmZ\">Инструкция для Windows</a> 🪟\n";
        $text .= "- <a href=\"https://teletype.in/@bott_manager/Q8vOQ-_lnQ_\">Инструкция для MacOS</a> 💻\n";
        $text .= "- <a href=\"https://teletype.in/@bott_manager/OIc2Dwer6jV\">Инструкция для AndroidTV</a> 📺\n\n";
        $text .= "👨🏻‍💻 По всем вопросам обращайтесь к <a href=\"ссылка на акк поддержки\">администратору</a> бота.\n\n";

        $this->sendMessage($text);
    }

    protected function handleKeyActivation(string $keyId): void
    {
        try {
            $key = $this->keyActivateRepository->findById($keyId);
            $botIdFromToken = explode(':', $key->packSalesman->salesman->token)[0];

            Log::debug('IDSToken: ' . $botIdFromToken . ' | CHAT: ' . $this->telegram->getMe()->id);

            if (!$key) {
                $this->sendMessage("❌ Ключ не найден.\n\nПожалуйста, проверьте правильность введенного ключа.");
                return;
            }

            if ($botIdFromToken != $this->telegram->getMe()->id) {
                $this->sendMessage("❌ Ключ не принадлежит боту активации.\n\nПожалуйста, проверьте правильность введенного ключа.");
                return;
            }

            // Проверяем статус ключа
            if ($key->status != KeyActivate::PAID) {
                $this->sendMessage("❌ Невозможно активировать ключ.\n\nКлюч уже был активирован ");
                return;
            }

            // Проверяем срок действия
            if ($key->finish_at && $key->finish_at < time()) {
                $this->sendMessage("❌ Срок действия ключа истек.\n\nПожалуйста, обратитесь к @admin для получения нового ключа.");
                return;
            }

            // Проверяем, не активирован ли уже ключ
            if ($key->user_tg_id) {
                $this->sendMessage("❌ Ключ уже был активирован.\n\nКаждый ключ можно использовать только один раз.");
                return;
            }

            // Активируем ключ через сервис
            $result = $this->keyActivateService->activate($key, $this->chatId);

            if ($result) {
                $this->sendSuccessActivation($key);
            } else {
                $this->sendMessage("❌ Не удалось активировать ключ.\n\nПожалуйста, попробуйте позже или обратитесь к @admin");
            }
        } catch (\Exception $e) {
            Log::error('Key activation error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    protected function sendSuccessActivation(KeyActivate $key): void
    {
        $finishDate = date('d.m.Y', $key->finish_at);

        $text = "✅ <b>VPN успешно активирован!</b>\n\n";
        $text .= "📅 Срок действия: до {$finishDate}\n\n";

        $text .= "🔗 <b>Ваша VPN-конфигурация:</b>\n\n";
        $text .= "[Открыть конфигурацию]\n";
        $text .= "https://vpn-telegram.com/config/{$key->id}\n\n";

        $text .= "📝 <b>Инструкция по настройке:</b>\n\n";
        $text .= "1️⃣ Установите VPN-клиент на Ваше устройство\n";
        $text .= "2️⃣ Скопируйте ссылку конфигурации выше\n";
        $text .= "3️⃣ Следуйте инструкциям для подключения на различных устройствах\n\n";


        $text .= "❓ Если возникли вопросы, обратитесь к администратору бота";
        $text .= "📱 Инструкции для настройки подключения:\n";

        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '🤖 Android',
                        'url' => 'https://teletype.in/@bott_manager/C0WFg-Bsren'
                    ]
                ],
                [
                    [
                        'text' => '🍏 iOS',
                        'url' => 'https://teletype.in/@bott_manager/8jEexiKqjlEWQ'
                    ]
                ],
                [
                    [
                        'text' => '🪟️ Windows',
                        'url' => 'https://teletype.in/@bott_manager/kJaChoXUqmZ'
                    ]
                ],
                [
                    [
                        'text' => '💻 MacOS',
                        'url' => 'https://teletype.in/@bott_manager/Q8vOQ-_lnQ_'
                    ]
                ],
                [
                    [
                        'text' => '📺 AndroidTV',
                        'url' => 'https://teletype.in/@bott_manager/OIc2Dwer6jV'
                    ]
                ]
            ]
        ];

        $this->sendMessage($text, $keyboard);
    }
}
