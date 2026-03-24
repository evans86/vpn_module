<?php

namespace App\Services\Telegram\ModuleBot;

use App\Logging\DatabaseLogger;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Salesman\Salesman;
use App\Models\TelegramUser\TelegramUser;
use App\Services\Panel\PanelStrategy;
use App\Support\KeyActivationMutex;
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
            // Логируем как warning, так как это может быть нормальная ситуация (старые вебхуки от удаленных/измененных ботов)
            Log::warning('Salesman not found for token: ' . substr($token, 0, 10) . '...', [
                'source' => 'telegram',
                'reason' => 'Token may be revoked, changed, or salesman deleted. This is normal for old webhooks.'
            ]);
            throw new RuntimeException('Salesman not found');
        }

        Log::info('Initialized SalesmanBotController', [
            'salesman_id' => $this->salesman->id,
            'token' => substr($token, 0, 10) . '...',
            'source' => 'telegram'
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
                        // Нормализуем ключ перед обработкой
                        $normalizedKey = $this->normalizeKeyText($text);
                        $this->handleKeyActivation($normalizedKey);
                    } else {
                        // Логируем для отладки, если текст похож на ключ, но не прошел валидацию
                        $trimmedText = trim($text);
                        if (preg_match('/[0-9a-f-]{20,}/i', $trimmedText)) {
                            Log::warning('Key format validation failed', [
                                'original_text' => $text,
                                'text_length' => strlen($text),
                                'text_hex' => bin2hex($text),
                                'trimmed_text' => $trimmedText,
                                'chat_id' => $this->chatId,
                                'source' => 'telegram'
                            ]);
                        }
                        $this->sendMessage('❌ Неизвестная команда. Воспользуйтесь меню для выбора действия.');
                    }
            }
        } catch (\Exception $e) {
            Log::error('Process update error: ' . $e->getMessage(), ['source' => 'telegram']);
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
                    'source' => 'telegram'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to ensure Telegram user exists: ' . $e->getMessage(), ['source' => 'telegram']);
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
            Log::error('Start command error: ' . $e->getMessage(), ['source' => 'telegram']);
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
            Log::error('Activate action error: ' . $e->getMessage(), ['source' => 'telegram']);
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
             * @var \Illuminate\Support\Collection $activeKeys
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
                    // Проверяем наличие связанных данных перед доступом
                    if (!$key->keyActivateUser ||
                        !$key->keyActivateUser->serverUser ||
                        !$key->keyActivateUser->serverUser->panel) {
                        Log::warning('Missing relationships for key', ['key_id' => $key->id, 'source' => 'telegram']);
                        $info = ['used_traffic' => null];
                    } else {
                        // Получаем информацию о трафике с панели
                        $panelStrategy = new PanelStrategy($key->keyActivateUser->serverUser->panel->panel);
                        $info = $panelStrategy->getSubscribeInfo(
                            $key->keyActivateUser->serverUser->panel->id,
                            $key->keyActivateUser->serverUser->id
                        );
                    }
                } catch (\Exception $e) {
                    // Логируем ошибку
                    Log::error('Failed to get subscription info for key ' . $key->id . ': ' . $e->getMessage(), ['source' => 'telegram']);
                    $info = ['used_traffic' => null];
                }

                $finishDate = date('d.m.Y', $key->finish_at);
                $daysRemaining = ceil(($key->finish_at - time()) / (60 * 60 * 24)); // Оставшиеся дни

                $message .= "🔑 *Подписка <code>{$key->id}</code>*\n";
                $message .= "📅 Действует до: {$finishDate}\n";
                $message .= "⏳ Осталось: {$daysRemaining} дней\n";

                if ($key->traffic_limit) {
                    $trafficGB = round($key->traffic_limit / (1024 * 1024 * 1024), 2);
//                    $message .= "📊 Лимит трафика: {$trafficGB} GB\n";
                }

                if ($info['used_traffic']) {
                    $trafficUsedGB = round($info['used_traffic'] / (1024 * 1024 * 1024), 2);
                    $message .= "📊 Использовано: {$trafficUsedGB} GB\n";
                }

                $message .= \App\Helpers\UrlHelper::telegramConfigLinksHtml($key->id, false) . "\n\n";
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
            Log::error('Status action error: ' . $e->getMessage() . ' | User ID: ' . $this->chatId . ' | Page: ' . $page, ['source' => 'telegram']);
            $this->sendErrorMessage();
        }
    }

    protected function actionInactiveSubscriptions(int $page = 0, ?int $messageId = null): void
    {
        try {
            $chatId = $this->chatId;
            $this->setCurrentPage($chatId, $page);

            /**
             * @var \Illuminate\Support\Collection $inactiveKeys
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
//                    $message .= "📊 Лимит трафика: {$trafficGB} GB\n";
                }

                $message .= \App\Helpers\UrlHelper::telegramConfigLinksHtml($key->id, false) . "\n\n";
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
            Log::error('Inactive subscriptions action error: ' . $e->getMessage() . ' | User ID: ' . $this->chatId . ' | Page: ' . $page, ['source' => 'telegram']);
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
//                $message .= "📊 Лимит трафика: {$trafficGB} GB\n";
            }

            if ($key->traffic_used) {
                $trafficUsedGB = round($key->traffic_used / (1024 * 1024 * 1024), 2);
                $message .= "📊 Использовано: {$trafficUsedGB} GB\n";
            }

            $message .= \App\Helpers\UrlHelper::telegramConfigLinksHtml($key->id, false) . "\n\n";

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
            Log::error('Subscription details error: ' . $e->getMessage() . ' | User ID: ' . $this->chatId . ' | Key ID: ' . $keyId, ['source' => 'telegram']);
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
            $keyHint = strlen($keyId) > 16
                ? substr($keyId, 0, 8) . '…' . substr($keyId, -4)
                : '[short]';
            app(DatabaseLogger::class)->info('Запрос активации ключа из бота (ввод ключа)', [
                'source' => 'key_activate',
                'action' => 'bot_activation_request',
                'key_id' => $keyId,
                'user_tg_id' => $this->chatId,
                'salesman_id' => $this->salesman->id,
                'key_hint' => $keyHint,
            ]);

            $key = $this->keyActivateRepository->findById($keyId);

            if (!$key) {
                $this->sendMessage("❌ Ключ не найден.\n\nПожалуйста, проверьте правильность введенного ключа.");
                return;
            }

            $botIdFromToken = explode(':', $key->packSalesman->salesman->token)[0];

            // Token validation check

            if ($botIdFromToken != $this->telegram->getMe()->id) {
                $this->sendMessage("❌ Ключ не принадлежит боту активации.\n\nПожалуйста, проверьте правильность введенного ключа.");
                return;
            }

            // Проверяем статус ключа (ACTIVATING — повтор при «зависшей» активации, без блокировки по user_tg_id)
            if ($key->status === KeyActivate::ACTIVE) {
                $this->sendMessage("❌ Невозможно активировать ключ.\n\nКлюч уже был активирован ");
                return;
            }
            if ($key->status !== KeyActivate::PAID && $key->status !== KeyActivate::ACTIVATING) {
                $this->sendMessage("❌ Невозможно активировать ключ.\n\nКлюч уже был активирован ");
                return;
            }
            if ($key->status === KeyActivate::ACTIVATING && (int) $key->user_tg_id !== (int) $this->chatId) {
                $this->sendMessage("❌ Ключ активируется другим пользователем.");
                return;
            }

            // Проверяем срок действия
            if ($key->finish_at && $key->finish_at < time()) {
                $this->sendMessage("❌ Срок действия ключа истек.\n\nПожалуйста, обратитесь к @admin для получения нового ключа.");
                return;
            }

            // Занят другим пользователем (не сценарий ACTIVATING тем же пользователем)
            if ($key->user_tg_id && (int) $key->user_tg_id !== (int) $this->chatId) {
                $this->sendMessage("❌ Ключ уже был активирован.\n\nКаждый ключ можно использовать только один раз.");
                return;
            }

            // Без Redis: MySQL GET_LOCK между воркерами; иначе file/database Cache::lock
            $activationLock = KeyActivationMutex::tryAcquire($keyId, (int) $this->chatId, 300);
            if ($activationLock === null) {
                $this->sendMessage(
                    "⏳ Этот ключ уже активируется.\n\nДождитесь сообщения о результате в этот чат — повторный запуск не нужен."
                );
                return;
            }

            try {
                // Сервисное сообщение: процесс пошёл (Marzban; прогрев конфига может идти после ответа)
                $this->sendMessage("⏳ Начался процесс активации ключа");

                app(DatabaseLogger::class)->info('Активация ключа: проверки и Telegram пройдены, вызов activate()', [
                    'source' => 'key_activate',
                    'action' => 'before_activate_service',
                    'key_id' => $keyId,
                    'user_tg_id' => $this->chatId,
                ]);

                $result = $this->keyActivateService->activate($key, $this->chatId);

                if ($result) {
                    $this->sendSuccessActivation($result);
                } else {
                    $this->sendMessage("❌ Не удалось активировать ключ.\n\nПожалуйста, попробуйте позже или обратитесь к @admin");
                }
            } finally {
                $activationLock->release();
            }
        } catch (\Exception $e) {
            Log::error('Key activation error: ' . $e->getMessage(), ['source' => 'telegram']);
            $keyRefreshed = $this->keyActivateRepository->findById($keyId);

            // Параллельный запрос: первый ещё в Marzban — второй получил «уже активируется» — не показываем общую ошибку
            $msg = $e->getMessage();
            if (strpos($msg, 'уже активируется') !== false || strpos($msg, 'подождите несколько') !== false) {
                if ($keyRefreshed && $keyRefreshed->status === KeyActivate::ACTIVE && (int) $keyRefreshed->user_tg_id === (int) $this->chatId) {
                    $this->sendSuccessActivation($keyRefreshed);
                    return;
                }
                $this->sendMessage(
                    "⏳ Активация ещё выполняется на сервере.\n\nПодождите 1–2 минуты — при успехе придёт сообщение с конфигурацией. Повторно вводить ключ не нужно."
                );
                return;
            }

            // При повторной доставке webhook: второй раз — ключ уже ACTIVE
            if ($keyRefreshed && $keyRefreshed->user_tg_id && $keyRefreshed->status === KeyActivate::ACTIVE) {
                if ((int) $keyRefreshed->user_tg_id === (int) $this->chatId) {
                    return;
                }
                $this->sendMessage("✅ Ключ уже был активирован ранее.\n\n" . \App\Helpers\UrlHelper::telegramConfigLinksHtml($keyRefreshed->id));
                return;
            }
            $this->sendErrorMessage();
        }
    }

    protected function sendSuccessActivation(KeyActivate $key): void
    {
        $finishDate = date('d.m.Y', $key->finish_at);

        $text = "✅ <b>VPN успешно активирован!</b>\n\n";
        $text .= "📅 Срок действия: до {$finishDate}\n\n";

        $text .= "🔗 <b>Ваша VPN-конфигурация:</b>\n\n";
        $text .= \App\Helpers\UrlHelper::telegramConfigLinksHtml($key->id) . "\n\n";

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
