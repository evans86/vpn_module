<?php

namespace App\Services\Telegram\ModuleBot;

use App\Models\KeyActivate\KeyActivate;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Salesman\Salesman;
use App\Services\Panel\PanelStrategy;
use DateInterval;
use DateTime;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Telegram\Bot\Api;
use Illuminate\Support\Facades\Log;

class FatherBotController extends AbstractTelegramBot
{
    private const STATE_WAITING_TOKEN = 'waiting_token';

    private const STATE_WAITING_HELP_TEXT = 'waiting_help_text';

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

            Log::channel('telegram')->info('Incoming update', [
                'update_id' => $this->update->getUpdateId(),
                'message_text' => $this->update->getMessage()->getText(),
                'chat_id' => $this->chatId
            ]);

            if ($callbackQuery) {
                Log::info('Вызов callback query', [
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

                if (str_starts_with($text, '/start')) {
                    Log::channel('telegram')->info('Start command received', [
                        'full_text' => $text,
                        'chat_id' => $this->chatId
                    ]);

                    if (str_contains($text, 'auth_')) {
                        $this->handleAuthRequest($text);
                        return;
                    }

                    $this->start();
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

                if ($salesman && $salesman->state === self::STATE_WAITING_HELP_TEXT) {
                    $this->handleHelpTextUpdate($text);
                    return;
                }

                // Проверяем, является ли сообщение ключом VPN
                if ($this->isValidKeyFormat($text)) {
                    $this->handleKeyInfoRequest($text);
                    return;
                }

                // Обработка команд меню
                switch ($text) {
                    case '🤖 Мой бот':
                        $this->showBotInfo();
                        break;
                    case '📦 Пакеты':
                        $this->showPacksList();
                        break;
                    case '🪪 Личный кабинет':
                        $this->showProfile();
                        break;
//                    case '🔑 Авторизация':
//                        $this->initiateAuth();
//                        break;
                    case '🌎 Помощь':
                        $this->showHelp();
                        break;
                    case '✏️ Изменить текст "❓ Помощь"':
                        $this->initiateHelpTextChange();
                        break;
                    case '🔄 Сбросить текст "❓ Помощь"':
                        $this->resetHelpText();
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

            $messageId = $this->update->getCallbackQuery()->getMessage()->getMessageId();

            switch ($params['action']) {
                case 'change_bot':
                    $this->initiateBotChange();
                    break;
                case 'show_pack':
                    if (isset($params['pack_id'])) {
                        $this->showPackDetails($params['pack_id']);
                    }
                    break;
                case 'export_keys':
                    if (isset($params['pack_id'])) {
                        $this->exportKeysToFile($params['pack_id']);
                    }
                    break;
                case 'export_keys_only':
                    if (isset($params['pack_id'])) {
                        $this->exportKeysOnlyToFile($params['pack_id']);
                    }
                    break;
                case 'export_unactivated_keys':
                    if (isset($params['pack_id'])) {
                        $this->exportUnactivatedKeysToFile($params['pack_id']);
                    }
                    break;
                case 'export_unactivated_keys_only':
                    if (isset($params['pack_id'])) {
                        $this->exportUnactivatedKeysOnlyToFile($params['pack_id']);
                    }
                    break;
                case 'export_keys_with_traffic':
                    if (isset($params['pack_id'])) {
                        $this->exportKeysWithTrafficToFile($params['pack_id']);
                    }
                    break;
                case 'export_keys_with_traffic_only':
                    if (isset($params['pack_id'])) {
                        $this->exportKeysWithTrafficOnlyToFile($params['pack_id']);
                    }
                    break;
                case 'export_used_keys':
                    if (isset($params['pack_id'])) {
                        $this->exportUsedKeysToFile($params['pack_id']);
                    }
                    break;
                case 'export_used_keys_only':
                    if (isset($params['pack_id'])) {
                        $this->exportUsedKeysOnlyToFile($params['pack_id']);
                    }
                    break;
                case 'show_packs':
                    $page = $params['page'] ?? 1;
                    $this->showPacksList($page, $messageId);
                    break;
                case 'packs_page':
                    if (isset($params['page'])) {
                        $this->showPacksList($params['page'], $messageId);
                    }
                    break;
                case 'toggle_bot':
                    $this->toggleBot($messageId);
                    break;
                case 'reload_bot':
                    $this->reloadBot();
                    break;

                case 'current_page':
                    // Просто отвечаем на callback query без изменений
                    $this->answerCallbackQuery('Вы уже на этой странице');
                    break;

//                case 'export_all_keys_menu':
//                    $this->exportAllKeysMenu();
//                    break;
//                case 'export_all_keys':
//                    $this->exportAllKeys();
//                    break;
//                case 'export_all_keys_only':
//                    $this->exportAllKeys(false);
//                    break;
//                case 'export_all_active_keys':
//                    $this->exportAllActiveKeys();
//                    break;
//                case 'export_all_active_keys_only':
//                    $this->exportAllActiveKeys(false);
//                    break;
//                case 'export_all_used_keys':
//                    $this->exportAllUsedKeys();
//                    break;
//                case 'export_all_used_keys_only':
//                    $this->exportAllUsedKeys(false);
//                    break;

                default:
                    Log::warning('Unknown callback action', [
                        'action' => $params['action'],
                        'data' => $data
                    ]);
            }
            // Всегда отвечаем на callback query чтобы убрать "loading"
            $this->answerCallbackQuery();

        } catch (Exception $e) {
            Log::error('Process callback error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $this->sendErrorMessage();
        }
    }

    /**
     * Ответ на callback query (для избежания "loading" состояния)
     */
    private function answerCallbackQuery(string $text = '', bool $showAlert = false): void
    {
        try {
            $callbackQueryId = $this->update->getCallbackQuery()->getId();
            $this->telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
                'show_alert' => $showAlert
            ]);
        } catch (\Exception $e) {
            Log::error('Error answering callback query: ' . $e->getMessage());
        }
    }

    /**
     *
     * @return void
     */
    protected function initiateAuth(): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();

            if (!$salesman) {
                $salesman = $this->salesmanService->create($this->chatId, $this->username ?? $this->firstName);
                $this->sendMessage("👋 Вы были автоматически зарегистрированы как продавец");
            }

            $botDeepLink = $this->generateAuthUrl();
            $hash = explode('auth_', $botDeepLink)[1];

            // Сохраняем в кэше информацию о том, что запрос идет из бота
            Cache::put("telegram_auth:{$hash}", [
                'user_id' => $this->chatId,
                'callback_url' => config('app.url') . '/personal/auth/telegram/callback',
                'source' => 'bot' // Добавляем метку источника
            ], now()->addMinutes(5));

            $message = "🔐 Для входа нажмите кнопку:\n";
            $message .= "1. Откроется Telegram\n";
            $message .= "2. Нажмите 'Start' в боте\n";
            $message .= "3. Подтвердите вход\n";

            $this->sendMessage($message, [
                'inline_keyboard' => [
                    [
                        [
                            'text' => '🔑 Войти в личный кабинет',
                            'url' => $botDeepLink
                        ]
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Auth initiation failed: ' . $e->getMessage());
            $this->sendMessage("❌ Ошибка: не удалось сформировать ссылку для входа");
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    public function generateAuthUrl(): string
    {
        $botUsername = ltrim(env('TELEGRAM_FATHER_BOT_NAME'), '@');
        if (empty($botUsername)) {
            throw new \Exception('Bot username not configured');
        }

        $hash = bin2hex(random_bytes(16));
        Cache::put("telegram_auth:{$hash}", [
            'user_id' => $this->chatId,
            'callback_url' => config('app.url') . '/personal/auth/telegram/callback'
        ], now()->addMinutes(5));

        return "https://t.me/{$botUsername}?start=auth_{$hash}";
    }

    /**
     * Формирование URL для авторизации
     *
     * @param string $commandText
     * @return void
     */
    private function handleAuthRequest(string $commandText): void
    {
        try {
            $hash = explode('auth_', $commandText)[1] ?? null;
            if (!$hash) {
                throw new \Exception('Invalid auth command format');
            }

            $authData = Cache::get("telegram_auth:{$hash}");
            if (!$authData) {
                throw new \Exception('Auth session expired or invalid');
            }

            // Всегда добавляем параметр redirect=profile
            $confirmationUrl = $authData['callback_url'] . '?' . http_build_query([
                    'hash' => $hash,
                    'user' => $authData['user_id'],
                    'redirect' => 'profile' // Жестко задаем редирект в профиль
                ]);

            $this->sendMessage(
                "✅ Для завершения авторизации нажмите кнопку:",
                [
                    'inline_keyboard' => [
                        [
                            [
                                'text' => 'Подтвердить вход',
                                'url' => $confirmationUrl
                            ]
                        ]
                    ]
                ]
            );

        } catch (\Exception $e) {
            Log::error('Auth processing failed: ' . $e->getMessage());
            $this->sendMessage("❌ Ошибка авторизации: " . $e->getMessage());
        }
    }

    /**
     * Валидация данных авторизации
     *
     * @param array $data
     * @return array|null
     */
    public function validateAuth(array $data): ?array
    {
        // 1. Проверка обязательных полей
        $requiredFields = ['id', 'auth_date', 'hash'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                Log::warning('Missing required field in Telegram auth data', ['field' => $field]);
                return null;
            }
        }

        // 2. Проверка временной метки (не старше 1 дня)
        $authDate = (int)$data['auth_date'];
        if (time() - $authDate > 86400) { // 24 часа
            Log::warning('Expired Telegram auth data', ['auth_date' => $authDate]);
            return null;
        }

        // 3. Верификация хэша (если есть все необходимые данные)
        if (!$this->verifyTelegramHash($data)) {
            Log::warning('Invalid Telegram hash verification');
            return null;
        }

        // 4. Подготовка и возврат данных пользователя
        return [
            'id' => (int)$data['id'],
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? null,
            'username' => $data['username'] ?? null,
            'photo_url' => $data['photo_url'] ?? null,
            'auth_date' => $authDate
        ];
    }

    /**
     * Обрабатывает запрос информации о ключе
     */
    protected function handleKeyInfoRequest(string $keyId): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
            if (!$salesman) {
                $this->sendMessage("❌ Ошибка: продавец не найден");
                return;
            }

            // Ищем ключ среди ключей этого продавца
            /**
             * @var KeyActivate|null $key
             */
            $key = KeyActivate::where('id', $keyId)
                ->whereHas('packSalesman', function ($query) use ($salesman) {
                    $query->where('salesman_id', $salesman->id);
                })
                ->with(['packSalesman.pack', 'keyActivateUser.serverUser.panel'])
                ->first();

            if (!$key) {
                $this->sendMessage("❌ Ключ <code>{$keyId}</code> не найден среди ваших ключей");
                return;
            }

            $message = "🔍 <b>Информация о ключе:</b> <code>{$keyId}</code>\n\n";

            // Основная информация
            $message .= "📦 <b>Пакет:</b> ";
            if ($key->packSalesman && $key->packSalesman->pack) {
                $message .= "# {$key->packSalesman->id} | ";
                $message .= "Период: {$key->packSalesman->pack->period} дней\n";
            } else {
                $message .= "неизвестен (возможно, пакет удален)\n";
            }

            // Статус ключа
            $status = "⚪️ Не активирован";
            if ($key->user_tg_id) {
                $status = "✅ Активирован (ID: {$key->user_tg_id})";
            } elseif ($key->status == KeyActivate::EXPIRED) {
                $status = "🔴 Просрочен";
            }
            $message .= "📊 <b>Статус:</b> {$status}\n";

            try {
                // Получаем информацию о трафике с панели
                $panelStrategy = new PanelStrategy($key->keyActivateUser->serverUser->panel->panel);
                $info = $panelStrategy->getSubscribeInfo($key->keyActivateUser->serverUser->panel->id, $key->keyActivateUser->serverUser->id);
            } catch (\Exception $e) {
                Log::error('Failed to get subscription info for key ' . $key->id . ': ' . $e->getMessage());
                $info = ['used_traffic' => null];
            }

            // Даты
            if ($key->created_at && !is_null($key->created_at)) {
                $message .= "📅 <b>Создан:</b> " . $key->created_at->format('d.m.Y H:i') . "\n";
            }

//            if ($key->deleted_at && !is_null($key->deleted_at)) {
//                $message .= "✅ <b>Активировать до:</b> " . date('d.m.Y', $key->deleted_at) . "\n";
//            }

            if ($key->finish_at && !is_null($key->finish_at)) {
                $message .= "⏳ <b>Действует до:</b> " . date('d.m.Y', $key->finish_at) . "\n";
                $message .= "⏳ <b>Осталось дней:</b> " . ceil(($key->finish_at - time()) / (60 * 60 * 24)) . "\n";
            }

            // Трафик
            if ($key->traffic_limit) {
                $trafficGB = number_format($key->traffic_limit / (1024 * 1024 * 1024), 2);
                $trafficUsedGB = round($info['used_traffic'] / (1024 * 1024 * 1024), 2);

                $message .= "📶 <b>Трафик:</b>\n";
                $message .= "   • Лимит: {$trafficGB} GB\n";
                $message .= "   • Использовано: {$trafficUsedGB} GB\n";
            }

            $this->sendMessage($message);

        } catch (Exception $e) {
            Log::error('Key info request error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Инициирует процесс изменения текста помощи
     */
    protected function initiateHelpTextChange(): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
            if (!$salesman) {
                $this->sendMessage("❌ Ошибка: продавец не найден");
                return;
            }

            $salesman->state = self::STATE_WAITING_HELP_TEXT;
            $salesman->save();

            $message = "✏️ <b>Введите новый текст для раздела '❓ Помощь' в вашем боте:</b>\n\n";
            $message .= "• Можно использовать HTML-разметку\n";
            $message .= "• Максимальная длина: 4000 символов\n";
            $message .= "• Отправьте /cancel для отмены";

            $this->sendMessage($message);
        } catch (Exception $e) {
            Log::error('Initiate help text change error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Обрабатывает обновление текста помощи
     */
    protected function handleHelpTextUpdate(string $text): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
            if (!$salesman) {
                $this->sendMessage("❌ Ошибка: продавец не найден");
                return;
            }

            if (strtolower($text) === '/cancel') {
                $salesman->state = null;
                $salesman->save();
                $this->sendMessage("❌ Изменение текста помощи отменено");
                return;
            }

            if (strlen($text) > 4000) {
                $this->sendMessage("❌ Текст слишком длинный (максимум 4000 символов)");
                return;
            }

            $salesman->custom_help_text = $text;
            $salesman->state = null;
            $salesman->save();

            $this->sendMessage("✅ Текст помощи успешно обновлен!\n\nПредпросмотр:\n\n" . $text);
        } catch (Exception $e) {
            Log::error('Help text update error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Сбрасывает текст помощи к стандартному
     */
    protected function resetHelpText(): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
            if (!$salesman) {
                $this->sendMessage("❌ Ошибка: продавец не найден");
                return;
            }

            $salesman->custom_help_text = null;
            $salesman->save();

            $this->sendMessage("✅ Текст помощи сброшен к стандартному");
        } catch (Exception $e) {
            Log::error('Reset help text error: ' . $e->getMessage());
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

            $message = "<i><b>Введите токен вашего бота:</b></i>\n\n";
            $message .= "🔑 <i><b>Как выпустить токен?</b></i>\n\n";
            $message .= "1️⃣ Открываем в телеграмме @BotFather и нажимаем start/начать\n\n";
            $message .= "2️⃣ Выбираем команду /newbot\n\n";
            $message .= "3️⃣ Вводим любое название для бота. Потом вводим никнейм бота на английском слитно, которое обязательно заканчивается на слово _bot\n\n";
            $message .= "4️⃣ Придёт сообщение, где после API будет находится наш токен.\n\n";

            $this->sendMessage($message);
        } catch (Exception $e) {
            Log::error('Initiate bot change error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

//    /**
//     * Показать список пакетов продавца с пагинацией
//     */
//    private function showPacksList(int $page = 1, ?int $messageId = null): void
//    {
//        try {
//            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
//            if (!$salesman) {
//                $this->sendMessage("❌ Ошибка: продавец не найден");
//                return;
//            }
//
//            // Количество пакетов на страницу
//            $perPage = 10;
//
//            // Получаем пакеты с пагинацией
//            $packs = PackSalesman::where('salesman_id', $salesman->id)
//                ->where('status', PackSalesman::PAID)
//                ->with('pack')
//                ->orderBy('created_at', 'desc')
//                ->paginate($perPage, ['*'], 'page', $page);
//
//            if ($packs->isEmpty()) {
//                $this->sendMessage("❌ Кажется, что у вас <b>нет</b> активных <b>пакетов</b>, успейте приобрести пакет ключей и начать свой бизнес!");
//                return;
//            }
//
//            $message = "<blockquote><b>📦 Пакеты ключей:</b></blockquote>\n\n";
//            $message .= "<b>✅ Для проверки конфигурации отправьте ключ боту.</b>\n\n";
//            $keyboard = ['inline_keyboard' => []];
//
//            // Добавляем пакеты на текущую страницу
//            foreach ($packs as $packSalesman) {
//                $pack = $packSalesman->pack;
//
//                // Проверяем, существует ли основной пакет
//                if ($pack) {
////                    $date = new DateTime($packSalesman->created_at);
////                    $date->add(new DateInterval("PT{$pack->activate_time}S"));
////                    $formattedDate = $date->format('d.m.Y');
//                    $traffic = number_format($pack->traffic_limit / (1024 * 1024 * 1024));
//
//                    $text = "📦{$traffic}GB| Период: {$pack->period}д";
//
////                    $text = "📦 Трафик: " . number_format($pack->traffic_limit / (1024 * 1024 * 1024), 1) . " GB|";
////                    $text .= "Период: {$pack->period} дней|";
////                    $text .= "Активировать до: {$formattedDate}";
//                } else {
//                    $text = "❌ Основной тариф удален";
//                }
//
//                $keyboard['inline_keyboard'][] = [
//                    [
//                        'text' => $text,
//                        'callback_data' => json_encode([
//                            'action' => 'show_pack',
//                            'pack_id' => $packSalesman->id
//                        ])
//                    ]
//                ];
//            }
//
//            // Добавляем кнопки пагинации
//            if ($packs->hasPages()) {
//                $paginationButtons = [];
//
//                // Кнопка "Назад"
//                if ($packs->currentPage() > 1) {
//                    $paginationButtons[] = [
//                        'text' => '⬅️ Назад',
//                        'callback_data' => json_encode([
//                            'action' => 'packs_page',
//                            'page' => $packs->currentPage() - 1
//                        ])
//                    ];
//                }
//
//                // Кнопка "Вперед"
//                if ($packs->hasMorePages()) {
//                    $paginationButtons[] = [
//                        'text' => 'Вперед ➡️',
//                        'callback_data' => json_encode([
//                            'action' => 'packs_page',
//                            'page' => $packs->currentPage() + 1
//                        ])
//                    ];
//                }
//
//                $keyboard['inline_keyboard'][] = $paginationButtons;
//            }
//
//            if ($messageId) {
//                $this->editMessage($message, $keyboard, $messageId);
//            } else {
//                $this->sendMessage($message, $keyboard);
//            }
//        } catch (\Exception $e) {
//            Log::error('Error in showPacksList: ' . $e->getMessage());
//            $this->sendErrorMessage();
//        }
//    }

    /**
     * Показать список пакетов продавца с пагинацией и красивым оформлением
     */
    private function showPacksList(int $page = 1, ?int $messageId = null): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
            if (!$salesman) {
                $this->sendMessage("❌ Ошибка: продавец не найден");
                return;
            }

            // Количество пакетов на страницу
            $perPage = 8;

            // Получаем ВСЕ пакеты для общей статистики
            $allPacks = PackSalesman::where('salesman_id', $salesman->id)
                ->where('status', PackSalesman::PAID)
                ->with('keyActivates')
                ->get();

            // Общая статистика по ВСЕМ пакетам
            $totalKeys = 0;
            $activeKeys = 0;
            $usedKeys = 0;

            foreach ($allPacks as $packSalesman) {
                $totalKeys += $packSalesman->keyActivates->count();
                $usedKeys += $packSalesman->keyActivates->whereNotNull('user_tg_id')->count();
            }
            $activeKeys = $totalKeys - $usedKeys;

            // Получаем пакеты для текущей страницы (только для отображения)
            $packs = PackSalesman::where('salesman_id', $salesman->id)
                ->where('status', PackSalesman::PAID)
                ->with(['pack', 'keyActivates'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            if ($packs->isEmpty()) {
                $message = "📦 <b>У вас пока нет активных пакетов</b>\n\n";
                $message .= "Чтобы начать продавать VPN:\n\n";
                $message .= "1️⃣ Пополните баланс в системе\n";
                $message .= "2️⃣ Приобретите пакеты VPN-ключей\n";
                $message .= "3️⃣ Начните продавать доступы клиентам\n\n";
                $message .= "⚡️ Первые продажи уже через 5 минут!";

                $this->sendMessage($message);
                return;
            }

            $message = "📊 <b>Ваши пакеты VPN-ключей</b>\n\n";
            $message .= "📈 <i>Общая статистика:</i>\n";
            $message .= "   • Всего ключей: <b>{$totalKeys}</b>\n";
            $message .= "   • Активных: <b>{$activeKeys}</b>\n";
            $message .= "   • Использовано: <b>{$usedKeys}</b>\n\n";

            // Добавляем информацию о текущей странице
            $currentPage = $packs->currentPage();
            $lastPage = $packs->lastPage();
            $totalPacks = $packs->total();

            $message .= "📦 <i>Пакеты на странице {$currentPage}/{$lastPage} (всего: {$totalPacks}):</i>\n\n";

            $keyboard = ['inline_keyboard' => []];

            // Добавляем пакеты с красивым оформлением
            foreach ($packs as $packSalesman) {
                $pack = $packSalesman->pack;

                // Статистика по ключам в этом пакете
                $totalPackKeys = $packSalesman->keyActivates->count();
                $usedPackKeys = $packSalesman->keyActivates->whereNotNull('user_tg_id')->count();
                $activePackKeys = $totalPackKeys - $usedPackKeys;

                // Процент использования
                $usagePercent = $totalPackKeys > 0 ? round(($usedPackKeys / $totalPackKeys) * 100) : 0;

                // Создаем прогресс-бар
                $progressBar = $this->createProgressBar($usagePercent);

                if ($pack) {
                    // Если основной пакет существует - показываем нормальную информацию
//                    $trafficGB = number_format($pack->traffic_limit / (1024 * 1024 * 1024));
                    $period = $pack->period;

                    $buttonText = "📦 {$period}д |\n";
                    $buttonText .= "{$progressBar} {$usagePercent}% |\n";
                    $buttonText .= "🔑 {$activePackKeys}/{$totalPackKeys}";
                } else {
                    // Если основной пакет удален - показываем альтернативную информацию
                    $buttonText = "📦 #Архивный пакет |\n";
                    $buttonText .= "{$progressBar} {$usagePercent}% |\n";
                    $buttonText .= "🔑 {$activePackKeys}/{$totalPackKeys}\n";
                }

                $keyboard['inline_keyboard'][] = [
                    [
                        'text' => $buttonText,
                        'callback_data' => json_encode([
                            'action' => 'show_pack',
                            'pack_id' => $packSalesman->id
                        ])
                    ]
                ];
            }

            // Добавляем кнопки пагинации с эмодзи
            if ($packs->hasPages()) {
                $paginationButtons = [];

                // Текущая страница и общее количество
                $currentPage = $packs->currentPage();
                $lastPage = $packs->lastPage();

                // Информация о странице
                $pageInfo = "📄 {$currentPage}/{$lastPage}";

                // Кнопка "В начало" если не на первой странице
                if ($currentPage > 1) {
                    $paginationButtons[] = [
                        'text' => '⏮',
                        'callback_data' => json_encode([
                            'action' => 'packs_page',
                            'page' => 1
                        ])
                    ];
                }

                // Кнопка "Назад"
                if ($currentPage > 1) {
                    $paginationButtons[] = [
                        'text' => '⬅️',
                        'callback_data' => json_encode([
                            'action' => 'packs_page',
                            'page' => $currentPage - 1
                        ])
                    ];
                }

                // Информация о странице
                $paginationButtons[] = [
                    'text' => $pageInfo,
                    'callback_data' => json_encode(['action' => 'current_page'])
                ];

                // Кнопка "Вперед"
                if ($packs->hasMorePages()) {
                    $paginationButtons[] = [
                        'text' => '➡️',
                        'callback_data' => json_encode([
                            'action' => 'packs_page',
                            'page' => $currentPage + 1
                        ])
                    ];
                }

                // Кнопка "В конец" если не на последней странице
                if ($currentPage < $lastPage) {
                    $paginationButtons[] = [
                        'text' => '⏭',
                        'callback_data' => json_encode([
                            'action' => 'packs_page',
                            'page' => $lastPage
                        ])
                    ];
                }

                $keyboard['inline_keyboard'][] = $paginationButtons;
            }

            // Добавляем кнопку обновления с временной меткой
//            $keyboard['inline_keyboard'][] = [
//                [
//                    'text' => '🔄 Обновить',
//                    'callback_data' => json_encode([
//                        'action' => 'show_packs',
//                        'page' => $page,
//                        'ts' => time()
//                    ])
//                ]
//            ];

            if ($messageId) {
                $this->editMessage($message, $keyboard, $messageId);
            } else {
                $this->sendMessage($message, $keyboard);
            }
        } catch (\Exception $e) {
            Log::error('Error in showPacksList: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

//    /**
//     * Показать список пакетов продавца с пагинацией и красивым оформлением
//     */
//    private function showPacksList(int $page = 1, ?int $messageId = null): void
//    {
//        try {
//            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
//            if (!$salesman) {
//                $this->sendMessage("❌ Ошибка: продавец не найден");
//                return;
//            }
//
//            // Количество пакетов на страницу
//            $perPage = 8;
//
////            // Получаем пакеты с пагинацией
////            $packs = PackSalesman::where('salesman_id', $salesman->id)
////                ->where('status', PackSalesman::PAID)
////                ->with(['pack', 'keyActivates'])
////                ->orderBy('created_at', 'desc')
////                ->paginate($perPage, ['*'], 'page', $page);
//
//            // Получаем пакеты с пагинацией
//            $packs = PackSalesman::where('salesman_id', $salesman->id)
//                ->where('status', PackSalesman::PAID)
//                ->with('pack')
//                ->orderBy('created_at', 'desc')
//                ->paginate($perPage, ['*'], 'page', $page);
//
//            if ($packs->isEmpty()) {
//                $message = "📦 <b>У вас пока нет активных пакетов</b>\n\n";
//                $message .= "Чтобы начать продавать VPN:\n\n";
//                $message .= "1️⃣ Пополните баланс в системе\n";
//                $message .= "2️⃣ Приобретите пакеты VPN-ключей\n";
//                $message .= "3️⃣ Начните продавать доступы клиентам\n\n";
//                $message .= "⚡️ Первые продажи уже через 5 минут!";
//
//                $this->sendMessage($message);
//                return;
//            }
//
//            // Заголовок с общей статистикой
//            $totalKeys = 0;
//            $activeKeys = 0;
//            $usedKeys = 0;
//
//            foreach ($packs as $packSalesman) {
//                $totalKeys += $packSalesman->keyActivates->count();
//                $usedKeys += $packSalesman->keyActivates->whereNotNull('user_tg_id')->count();
//            }
//            $activeKeys = $totalKeys - $usedKeys;
//
//            $message = "📊 <b>Ваши пакеты VPN-ключей</b>\n\n";
//            $message .= "📈 <i>Общая статистика:</i>\n";
//            $message .= "   • Всего ключей: <b>{$totalKeys}</b>\n";
//            $message .= "   • Активных: <b>{$activeKeys}</b>\n";
//            $message .= "   • Использовано: <b>{$usedKeys}</b>\n\n";
//            $message .= "🔍 <i>Выберите пакет для просмотра деталей:</i>\n\n";
//
//            $keyboard = ['inline_keyboard' => []];
//
//            // Добавляем пакеты с красивым оформлением
//            foreach ($packs as $packSalesman) {
//                $pack = $packSalesman->pack;
//
//                if (!$pack) {
//                    continue; // Пропускаем если пакет удален
//                }
//
//                // Статистика по ключам в этом пакете
//                $totalPackKeys = $packSalesman->keyActivates->count();
//                $usedPackKeys = $packSalesman->keyActivates->whereNotNull('user_tg_id')->count();
//                $activePackKeys = $totalPackKeys - $usedPackKeys;
//
//                // Процент использования
//                $usagePercent = $totalPackKeys > 0 ? round(($usedPackKeys / $totalPackKeys) * 100) : 0;
//
//                // Форматируем трафик
//                $period = $pack->period;
//
//                // Создаем прогресс-бар
//                $progressBar = $this->createProgressBar($usagePercent);
//
//                // Текст кнопки
//                $buttonText = "📦 {$period}д\n";
//                $buttonText = "{$progressBar} {$usagePercent}%\n";
//                $buttonText .= "🔑 {$activePackKeys}/{$totalPackKeys}";
//
//                $keyboard['inline_keyboard'][] = [
//                    [
//                        'text' => $buttonText,
//                        'callback_data' => json_encode([
//                            'action' => 'show_pack',
//                            'pack_id' => $packSalesman->id
//                        ])
//                    ]
//                ];
//            }
//
////            // Добавляем разделитель
////            $keyboard['inline_keyboard'][] = [
////                [
////                    'text' => '📥 Выгрузить все ключи',
////                    'callback_data' => json_encode([
////                        'action' => 'export_all_keys_menu'
////                    ])
////                ]
////            ];
//
//            // Добавляем кнопки пагинации с эмодзи
//            if ($packs->hasPages()) {
//                $paginationButtons = [];
//
//                // Текущая страница и общее количество
//                $currentPage = $packs->currentPage();
//                $lastPage = $packs->lastPage();
//
//                // Информация о странице
//                $pageInfo = "📄 {$currentPage}/{$lastPage}";
//
//                if ($currentPage > 1) {
//                    $paginationButtons[] = [
//                        'text' => '⬅️',
//                        'callback_data' => json_encode([
//                            'action' => 'packs_page',
//                            'page' => $currentPage - 1
//                        ])
//                    ];
//                }
//
//                $paginationButtons[] = [
//                    'text' => $pageInfo,
//                    'callback_data' => json_encode(['action' => 'current_page'])
//                ];
//
//                if ($packs->hasMorePages()) {
//                    $paginationButtons[] = [
//                        'text' => '➡️',
//                        'callback_data' => json_encode([
//                            'action' => 'packs_page',
//                            'page' => $currentPage + 1
//                        ])
//                    ];
//                }
//
//                $keyboard['inline_keyboard'][] = $paginationButtons;
//            }
//
//            if ($messageId) {
//                $this->editMessage($message, $keyboard, $messageId);
//            } else {
//                $this->sendMessage($message, $keyboard);
//            }
//        } catch (\Exception $e) {
//            Log::error('Error in showPacksList: ' . $e->getMessage());
//            $this->sendErrorMessage();
//        }
//    }

    /**
     * Создает текстовый прогресс-бар
     */
    private function createProgressBar(int $percent): string
    {
        $filled = round($percent / 10);
        $empty = 10 - $filled;

        $bar = '';
        for ($i = 0; $i < $filled; $i++) {
            $bar .= '█';
        }
        for ($i = 0; $i < $empty; $i++) {
            $bar .= '░';
        }

        return $bar;
    }

//    /**
//     * Показать детали пакета и его ключи
//     */
//    private function showPackDetails(int $packSalesmanId): void
//    {
//        try {
//            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
//            if (!$salesman) {
//                $this->sendMessage("❌ Ошибка: продавец не найден");
//                return;
//            }
//
//            $packSalesman = PackSalesman::with(['pack', 'keyActivates'])
//                ->where('id', $packSalesmanId)
//                ->where('salesman_id', $salesman->id)
//                ->firstOrFail();
//
//            $pack = $packSalesman->pack;
//            $keys = $packSalesman->keyActivates;
//
//            // Основное сообщение
//            $message = "<b>📦 Информация о пакете:</b>\n\n";
//
//            if ($pack) {
//                $message .= "💾 Трафик: " . number_format($pack->traffic_limit / (1024 * 1024 * 1024), 1) . " GB\n";
//                $message .= "⏱ Период: {$pack->period} дней\n\n";
//            } else {
//                $message .= "❌ Основной тариф удален\n\n";
//            }
//
//            // Добавляем ключи активации
//            $message .= "<b>🔑 Ключи активации:</b>\n";
//            foreach ($keys as $index => $key) {
//                $status = $key->user_tg_id ? "✅ Активирован" : "⚪️ Не активирован";
//                $message .= ($index + 1) . ". <code>{$key->id}</code> - {$status}" .
//                    ($key->user_tg_id ? " (ID: {$key->user_tg_id})" : "") . "\n";
//            }
//
//            // Кнопки для выгрузки ключей в .txt файл
//            $keyboard = [
//                'inline_keyboard' => [
//                    [
//                        [
//                            'text' => '📥 Выгрузить все ключи',
//                            'callback_data' => json_encode([
//                                'action' => 'export_keys',
//                                'pack_id' => $packSalesmanId
//                            ])
//                        ],
//                        [
//                            'text' => '(Без текста)',
//                            'callback_data' => json_encode([
//                                'action' => 'export_keys_only',
//                                'pack_id' => $packSalesmanId
//                            ])
//                        ]
//                    ],
//                    [
//                        [
//                            'text' => '📥 Выгрузить не активированные',
//                            'callback_data' => json_encode([
//                                'action' => 'export_unactivated_keys',
//                                'pack_id' => $packSalesmanId
//                            ])
//                        ],
//                        [
//                            'text' => '(Без текста)',
//                            'callback_data' => json_encode([
//                                'action' => 'export_unactivated_keys_only',
//                                'pack_id' => $packSalesmanId
//                            ])
//                        ]
//                    ],
//                    [
//                        [
//                            'text' => '📥 Выгрузить использованные',
//                            'callback_data' => json_encode([
//                                'action' => 'export_used_keys',
//                                'pack_id' => $packSalesmanId
//                            ])
//                        ],
//                        [
//                            'text' => '(Без текста)',
//                            'callback_data' => json_encode([
//                                'action' => 'export_used_keys_only',
//                                'pack_id' => $packSalesmanId
//                            ])
//                        ]
//                    ]
//                ]
//            ];
//
//            // Проверяем длину сообщения
//            if (strlen($message) <= 4096) {
//                $this->sendMessage($message, $keyboard);
//            } else {
//                // Если сообщение слишком длинное, сначала отправляем информацию о пакете
//                $packInfo = "<b>📦 Информация о пакете:</b>\n\n";
//                if ($pack) {
//                    $packInfo .= "💾 Трафик: " . number_format($pack->traffic_limit / (1024 * 1024 * 1024), 1) . " GB\n";
//                    $packInfo .= "⏱ Период: {$pack->period} дней\n\n";
//                } else {
//                    $packInfo .= "❌ Основной тариф удален\n\n";
//                }
//                $this->sendMessage($packInfo);
//
//                // Затем отправляем ключи частями
//                $chunkSize = 50;
//                $keyChunks = $keys->chunk($chunkSize);
//                foreach ($keyChunks as $index => $chunk) {
//                    $keyMessage = "<b>🔑 Ключи активации (часть " . ($index + 1) . "):</b>\n";
//                    foreach ($chunk as $keyIndex => $key) {
//                        $status = $key->user_tg_id ? "✅ Активирован" : "⚪️ Не активирован";
//                        $keyMessage .= ($index * $chunkSize + $keyIndex + 1) . ". <code>{$key->id}</code> - {$status}" .
//                            ($key->user_tg_id ? " (ID: {$key->user_tg_id})" : "") . "\n";
//                    }
//                    $this->sendMessage($keyMessage);
//                }
//                // Отправляем кнопку после всех ключей
//                $this->sendMessage("Вы можете выгрузить все ключи в .txt файл:", $keyboard);
//            }
//        } catch (\Exception $e) {
//            Log::error('Error in showPackDetails: ' . $e->getMessage());
//            $this->sendErrorMessage();
//        }
//    }

    /**
     * Показать детали пакета с улучшенным оформлением
     */
    private function showPackDetails(int $packSalesmanId): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
            if (!$salesman) {
                $this->sendMessage("❌ Ошибка: продавец не найден");
                return;
            }

            $packSalesman = PackSalesman::with(['pack', 'keyActivates'])
                ->where('id', $packSalesmanId)
                ->where('salesman_id', $salesman->id)
                ->firstOrFail();

            $pack = $packSalesman->pack;
            $keys = $packSalesman->keyActivates;

            // Статистика по ключам
            $totalKeys = $keys->count();
            $usedKeys = $keys->whereNotNull('user_tg_id')->count();
            $activeKeys = $totalKeys - $usedKeys;
            $usagePercent = $totalKeys > 0 ? round(($usedKeys / $totalKeys) * 100) : 0;

            // Основное сообщение
            $message = "📦 <b>Детали пакета</b>\n\n";

            if ($pack) {
                // Если основной пакет существует
//                $trafficGB = number_format($pack->traffic_limit / (1024 * 1024 * 1024), 1);
//                $message .= "💾 <b>Трафик:</b> {$trafficGB} GB\n";
                $message .= "⏱ <b>Период:</b> {$pack->period} дней\n";
            } else {
                // Если основной пакет удален
                $message .= "ℹ️ <b>Тип пакета:</b> Архивный\n";
            }

            $message .= "📅 <b>Создан:</b> " . $packSalesman->created_at->format('d.m.Y H:i') . "\n\n";

            // Статистика использования
            $progressBar = $this->createProgressBar($usagePercent);
            $message .= "📊 <b>Использование ключей:</b>\n";
            $message .= "{$progressBar} {$usagePercent}%\n";
            $message .= "✅ <b>Активных:</b> {$activeKeys} ключей\n";
            $message .= "🔒 <b>Использовано:</b> {$usedKeys} ключей\n";
            $message .= "📋 <b>Всего:</b> {$totalKeys} ключей\n\n";

            if (!$pack) {
                $message .= "💡 <i>Это архивный пакет. Основной тариф был обновлен, но ваши ключи остаются активными.</i>\n\n";
            }

            $message .= "🔍 <b>Для проверки ключа отправьте его боту</b>";

            // Кнопки управления
            $keyboard = [
                'inline_keyboard' => [
                    // Основные действия
                    [
                        [
                            'text' => '📥 Выгрузить все ключи',
                            'callback_data' => json_encode([
                                'action' => 'export_keys',
                                'pack_id' => $packSalesmanId
                            ])
                        ],
                        [
                            'text' => '📋 (Только ключи)',
                            'callback_data' => json_encode([
                                'action' => 'export_keys_only',
                                'pack_id' => $packSalesmanId
                            ])
                        ]
                    ],
                    // Фильтры
                    [
                        [
                            'text' => '🟢 Активные ключи',
                            'callback_data' => json_encode([
                                'action' => 'export_unactivated_keys',
                                'pack_id' => $packSalesmanId
                            ])
                        ],
                        [
                            'text' => '📋 (Только ключи)',
                            'callback_data' => json_encode([
                                'action' => 'export_unactivated_keys_only',
                                'pack_id' => $packSalesmanId
                            ])
                        ]
                    ],
                    [
                        [
                            'text' => '🔴 Использованные',
                            'callback_data' => json_encode([
                                'action' => 'export_used_keys',
                                'pack_id' => $packSalesmanId
                            ])
                        ],
                        [
                            'text' => '📋 (Только ключи)',
                            'callback_data' => json_encode([
                                'action' => 'export_used_keys_only',
                                'pack_id' => $packSalesmanId
                            ])
                        ]
                    ],
                    // Навигация
                    [
                        [
                            'text' => '⬅️ Назад к списку',
                            'callback_data' => json_encode([
                                'action' => 'show_packs',
                                'page' => 1
                            ])
                        ]
                    ]
                ]
            ];

            $this->sendMessage($message, $keyboard);

        } catch (\Exception $e) {
            Log::error('Error in showPackDetails: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

//    /**
//     * Меню выгрузки всех ключей
//     */
//    private function exportAllKeysMenu(): void
//    {
//        try {
//            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
//            if (!$salesman) {
//                $this->sendMessage("❌ Ошибка: продавец не найден");
//                return;
//            }
//
//            $message = "📥 <b>Выгрузка всех ключей</b>\n\n";
//            $message .= "Выберите тип выгрузки:\n\n";
//            $message .= "• <b>Все ключи</b> - полный список всех ключей\n";
//            $message .= "• <b>Активные</b> - только неиспользованные ключи\n";
//            $message .= "• <b>Использованные</b> - только активированные ключи\n";
//
//            $keyboard = [
//                'inline_keyboard' => [
//                    [
//                        [
//                            'text' => '📥 Все ключи',
//                            'callback_data' => json_encode(['action' => 'export_all_keys'])
//                        ],
//                        [
//                            'text' => '📋 (Только ключи)',
//                            'callback_data' => json_encode(['action' => 'export_all_keys_only'])
//                        ]
//                    ],
//                    [
//                        [
//                            'text' => '🟢 Активные ключи',
//                            'callback_data' => json_encode(['action' => 'export_all_active_keys'])
//                        ],
//                        [
//                            'text' => '📋 (Только ключи)',
//                            'callback_data' => json_encode(['action' => 'export_all_active_keys_only'])
//                        ]
//                    ],
//                    [
//                        [
//                            'text' => '🔴 Использованные',
//                            'callback_data' => json_encode(['action' => 'export_all_used_keys'])
//                        ],
//                        [
//                            'text' => '📋 (Только ключи)',
//                            'callback_data' => json_encode(['action' => 'export_all_used_keys_only'])
//                        ]
//                    ],
//                    [
//                        [
//                            'text' => '⬅️ Назад',
//                            'callback_data' => json_encode(['action' => 'show_packs', 'page' => 1])
//                        ]
//                    ]
//                ]
//            ];
//
//            $this->sendMessage($message, $keyboard);
//
//        } catch (\Exception $e) {
//            Log::error('Error in exportAllKeysMenu: ' . $e->getMessage());
//            $this->sendErrorMessage();
//        }
//    }

//    /**
//     * Выгрузка всех ключей продавца
//     */
//    private function exportAllKeys(bool $withText = true): void
//    {
//        try {
//            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
//            if (!$salesman) {
//                $this->sendMessage("❌ Ошибка: продавец не найден");
//                return;
//            }
//
//            $allPacks = PackSalesman::where('salesman_id', $salesman->id)
//                ->where('status', PackSalesman::PAID)
//                ->with('keyActivates')
//                ->get();
//
//            $content = "";
//            if ($withText) {
//                $content .= "Все ключи продавца\n";
//                $content .= "Telegram ID: {$salesman->telegram_id}\n";
//                $content .= "Бот: {$salesman->bot_link}\n";
//                $content .= "Дата выгрузки: " . date('d.m.Y H:i') . "\n\n";
//            }
//
//            $totalKeys = 0;
//            foreach ($allPacks as $pack) {
//                foreach ($pack->keyActivates as $key) {
//                    $content .= "{$key->id}\n";
//                    $totalKeys++;
//                }
//            }
//
//            if ($withText) {
//                $content .= "\nВсего ключей: {$totalKeys}";
//            }
//
//            $this->sendKeysFile($content, "all_keys_{$salesman->telegram_id}.txt", "Все ключи ({$totalKeys})");
//
//        } catch (\Exception $e) {
//            Log::error('Error in exportAllKeys: ' . $e->getMessage());
//            $this->sendErrorMessage();
//        }
//    }


    /**
     * Выгрузить все ключи | (Без текста)
     *
     * @param int $packSalesmanId
     * @return void
     */
    private function exportKeysOnlyToFile(int $packSalesmanId): void
    {
        $this->exportKeysToFile($packSalesmanId, false);
    }

    /**
     * Выгрузить не активированные | (Без текста)
     *
     * @param int $packSalesmanId
     * @return void
     */
    private function exportUnactivatedKeysOnlyToFile(int $packSalesmanId): void
    {
        $this->exportUnactivatedKeysToFile($packSalesmanId, false);
    }

    /**
     * Выгрузить с остатком трафика | (Без текста)
     *
     * @param int $packSalesmanId
     * @return void
     */
    private function exportKeysWithTrafficOnlyToFile(int $packSalesmanId): void
    {
        $this->exportKeysWithTrafficToFile($packSalesmanId, false);
    }

    /**
     * Выгрузить использованные | (Без текста)
     *
     * @param int $packSalesmanId
     * @return void
     */
    private function exportUsedKeysOnlyToFile(int $packSalesmanId): void
    {
        $this->exportUsedKeysToFile($packSalesmanId, false);
    }

    /**
     * Выгрузить все ключи
     *
     * @param int $packSalesmanId
     * @param bool $withText
     * @return void
     */
    private function exportKeysToFile(int $packSalesmanId, bool $withText = true): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
            if (!$salesman) {
                $this->sendMessage("❌ Ошибка: продавец не найден");
                return;
            }

            $packSalesman = PackSalesman::with(['pack', 'keyActivates'])
                ->where('id', $packSalesmanId)
                ->where('salesman_id', $salesman->id)
                ->firstOrFail();

            $pack = $packSalesman->pack;
            $keys = $packSalesman->keyActivates;

            // Создаем содержимое файла
            $content = "";
            if ($withText) {
                $content .= "Пакет: ID {$packSalesman->id}\n";
//                $content .= "Трафик: " . number_format($pack->traffic_limit / (1024 * 1024 * 1024), 1) . " GB\n";
                if ($pack) {
                    $content .= "Период: {$pack->period} дней\n";
                } else {
                    $content .= "Тип: Архивный пакет\n";
                }
                $content .= "Ключи можно активировать в боте: $salesman->bot_link\n\n";
                $content .= "Ключи активации:\n";
            }

            foreach ($keys as $index => $key) {
                $content .= "{$key->id}\n";
            }

            // Создаем временный файл
            $fileName = "keys_{$packSalesman->id}.txt";
            $tempPath = storage_path('app/temp/' . $fileName);

            // Создаем директорию если её нет
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0777, true);
            }

            // Записываем содержимое в файл
            file_put_contents($tempPath, $content);

            // Отправляем файл
            $this->telegram->sendDocument([
                'chat_id' => $this->chatId,
                'document' => fopen($tempPath, 'r'),
                'caption' => "📥 Выгрузка ключей"
            ]);

            // Удаляем временный файл
            unlink($tempPath);
        } catch (\Exception $e) {
            Log::error('Error in exportKeysToFile: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Выгрузить не активированные
     *
     * @param int $packSalesmanId
     * @param bool $withText
     * @return void
     */
    private function exportUnactivatedKeysToFile(int $packSalesmanId, bool $withText = true): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
            if (!$salesman) {
                $this->sendMessage("❌ Ошибка: продавец не найден");
                return;
            }

            $packSalesman = PackSalesman::with(['pack', 'keyActivates'])
                ->where('id', $packSalesmanId)
                ->where('salesman_id', $salesman->id)
                ->firstOrFail();

            $pack = $packSalesman->pack;
            $keys = $packSalesman->keyActivates->whereNull('user_tg_id');

            // Создаем содержимое файла
            $content = "";
            if ($withText) {
                $content .= "Пакет: ID {$packSalesman->id}\n";
//                $content .= "Трафик: " . number_format($pack->traffic_limit / (1024 * 1024 * 1024), 1) . " GB\n";
                if ($pack) {
                    $content .= "Период: {$pack->period} дней\n";
                } else {
                    $content .= "Тип: Архивный пакет\n";
                }
                $content .= "Ключи можно активировать в боте: $salesman->bot_link\n\n";
                $content .= "Не активированные ключи активации:\n";
            }

//            if (!empty($keys))
//                $content .= "Нет не активированных ключей";

            foreach ($keys as $index => $key) {
                $content .= "{$key->id}\n";
            }

            // Создаем временный файл
            $fileName = "unactivated_keys_{$packSalesman->id}.txt";
            $tempPath = storage_path('app/temp/' . $fileName);

            // Создаем директорию если её нет
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0777, true);
            }

            // Записываем содержимое в файл
            file_put_contents($tempPath, $content);

            // Отправляем файл
            $this->telegram->sendDocument([
                'chat_id' => $this->chatId,
                'document' => fopen($tempPath, 'r'),
                'caption' => "📥 Выгрузка не активированных ключей"
            ]);

            // Удаляем временный файл
            unlink($tempPath);
        } catch (\Exception $e) {
            Log::error('Error in exportUnactivatedKeysToFile: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Выгрузить с остатком трафика
     *
     * @param int $packSalesmanId
     * @param bool $withText
     * @return void
     */
    private function exportKeysWithTrafficToFile(int $packSalesmanId, bool $withText = true): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
            if (!$salesman) {
                $this->sendMessage("❌ Ошибка: продавец не найден");
                return;
            }

            $packSalesman = PackSalesman::with(['pack', 'keyActivates'])
                ->where('id', $packSalesmanId)
                ->where('salesman_id', $salesman->id)
                ->firstOrFail();

            $pack = $packSalesman->pack;
            $keys = $packSalesman->keyActivates->where('traffic_used', '<', $pack->traffic_limit);

            // Создаем содержимое файла
            $content = "";
            if ($withText) {
                $content .= "Пакет: ID {$packSalesman->id}\n";
//                $content .= "Трафик: " . number_format($pack->traffic_limit / (1024 * 1024 * 1024), 1) . " GB\n";
                if ($pack) {
                    $content .= "Период: {$pack->period} дней\n";
                } else {
                    $content .= "Тип: Архивный пакет\n";
                }
                $content .= "Ключи можно активировать в боте: $salesman->bot_link\n\n";
                $content .= "Ключи с остатком трафика:\n";
            }

            foreach ($keys as $index => $key) {
                $content .= "{$key->id}\n";
            }

            // Создаем временный файл
            $fileName = "keys_with_traffic_{$packSalesman->id}.txt";
            $tempPath = storage_path('app/temp/' . $fileName);

            // Создаем директорию если её нет
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0777, true);
            }

            // Записываем содержимое в файл
            file_put_contents($tempPath, $content);

            // Отправляем файл
            $this->telegram->sendDocument([
                'chat_id' => $this->chatId,
                'document' => fopen($tempPath, 'r'),
                'caption' => "📥 Выгрузка ключей с остатком трафика"
            ]);

            // Удаляем временный файл
            unlink($tempPath);
        } catch (\Exception $e) {
            Log::error('Error in exportKeysWithTrafficToFile: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Выгрузить использованные
     *
     * @param int $packSalesmanId
     * @param bool $withText
     * @return void
     */
    private function exportUsedKeysToFile(int $packSalesmanId, bool $withText = true): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
            if (!$salesman) {
                $this->sendMessage("❌ Ошибка: продавец не найден");
                return;
            }

            $packSalesman = PackSalesman::with(['pack', 'keyActivates'])
                ->where('id', $packSalesmanId)
                ->where('salesman_id', $salesman->id)
                ->firstOrFail();

            $pack = $packSalesman->pack;
            $keys = $packSalesman->keyActivates->whereNotNull('user_tg_id');

            // Создаем содержимое файла
            $content = "";
            if ($withText) {
                $content .= "Пакет: ID {$packSalesman->id}\n";
//                $content .= "Трафик: " . number_format($pack->traffic_limit / (1024 * 1024 * 1024), 1) . " GB\n";
                if ($pack) {
                    $content .= "Период: {$pack->period} дней\n";
                } else {
                    $content .= "Тип: Архивный пакет\n";
                }
                $content .= "Ключи можно активировать в боте: $salesman->bot_link\n\n";
                $content .= "Использованные ключи:\n";
            }

            foreach ($keys as $index => $key) {
                $content .= "{$key->id}\n";
            }

            // Создаем временный файл
            $fileName = "used_keys_{$packSalesman->id}.txt";
            $tempPath = storage_path('app/temp/' . $fileName);

            // Создаем директорию если её нет
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0777, true);
            }

            // Записываем содержимое в файл
            file_put_contents($tempPath, $content);

            // Отправляем файл
            $this->telegram->sendDocument([
                'chat_id' => $this->chatId,
                'document' => fopen($tempPath, 'r'),
                'caption' => "📥 Выгрузка использованных ключей"
            ]);

            // Удаляем временный файл
            unlink($tempPath);
        } catch (\Exception $e) {
            Log::error('Error in exportUsedKeysToFile: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

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

//            $message = "👋 <i>Добро пожаловать в систему управления VPN-доступами!</i>\n\n\n";
//            $message .= "🌍 <b>Хотите зарабатывать на продаже VPN?</b> С нами это просто и удобно!\n\n\n";
//            $message .= "🚀 <i><b>Что вы получите:</b></i>\n\n";
//            $message .= "🔹 <i>Готовую систему</i> - покупайте пакеты ключей и создавайте своего бота за считанные минуты\n\n";
//            $message .= "🔹 <i>Автоматизацию</i> - Ваш бот сам выдает доступы клиентам 24/7\n\n";
//            $message .= "🔹 <i>Гибкость</i> - выбирайте тарифы, управляйте ценами и следите за балансом\n\n";
//            $message .= "🔹 <i>Высокий спрос</i> - VPN нужен многим, а значит, клиентов будет достаточно!\n\n";
//            $message .= "🔹 <i>Простоту подключения</i> - без сложных настроек, просто привяжите своего бота\n\n\n";
//            $message .= "💼  <i><b>Как начать?</b></i>\n\n";
//            $message .= "1️⃣ Купите пакет VPN-ключей\n\n";
//            $message .= "2️⃣ Привяжите своего бота к системе\n\n";
//            $message .= "3️⃣ Начните продавать доступы и зарабатывать\n\n\n";
//            $message .= "📲 Подключайтесь и создавайте свой бизнес на продаже VPN уже сегодня!\n";
//            $message .= "<b>Приятного пользования!</b>\n";

            $message = "👋 <b>Добро пожаловать в систему управления VPN-доступами!</b>\n\n";
            $message .= "<i>Это ваш личный кабинет для запуска и управления бизнесом по продаже VPN.</i>\n\n";
            $message .= "🚀 <b>Чтобы начать зарабатывать, нужно всего 3 шага:</b>\n\n";
            $message .= "1️⃣ <b>Добавьте своего бота</b>\n";
            $message .= "   • Создайте бота через <a href=\"https://t.me/BotFather\">@BotFather</a>\n";
            $message .= "   • Получите токен и привяжите его здесь через кнопку <b>\"🤖 Мой бот\"</b>\n\n";
            $message .= "2️⃣ <b>Пополните баланс и купите пакеты</b>\n";
            $message .= "   • Пополните баланс в системе\n";
            $message .= "   • Приобретите пакеты VPN-ключей для продажи\n\n";
            $message .= "3️⃣ <b>Настройте модуль и начинайте продавать</b>\n";
            $message .= "   • Интегрируйте VPN-модуль в своего бота\n";
            $message .= "   • Ваши клиенты смогут покупать доступы автоматически 24/7\n\n";
            $message .= "💡 <b>Что вы получаете:</b>\n";
            $message .= "• 4 протокола: Vless/Vmess/Shadowsocks/Trojan\n";
            $message .= "• Безлимитный трафик\n";
            $message .= "• Поддержку всех устройств (Android, iOS, Windows, MacOS, Android TV)\n";
            $message .= "• Автоматическую выдачу ключей\n";
            $message .= "• Готовые инструкции для клиентов\n\n";
            $message .= "📚 <b>Нужна помощь?</b>\n";
            $message .= "• Подробная инструкция по созданию бота: кнопка <b>\"❓ Помощь\"</b>\n\n";
            $message .= "<i>Выберите следующий шаг в меню ниже ↓</i>";

            $this->generateMenu($message);
        } catch (\Exception $e) {
            Log::error('Start command error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Генерация меню
     */
    protected function generateMenu($message = null): void
    {
        $keyboard = [
            'keyboard' => [
                [
                    ['text' => '🤖 Мой бот'],
                    ['text' => '📦 Пакеты']
                ],
                [
                    ['text' => '🪪 Личный кабинет'],
//                    ['text' => '🔑 Авторизация'],
                    ['text' => '🌎 Помощь']
                ],
                [
                    ['text' => '✏️ Изменить текст "❓ Помощь"'],
                    ['text' => '🔄 Сбросить текст "❓ Помощь"']
                ]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];

        if ($message) {
            $this->sendMessage($message, $keyboard);
        } else {
            $this->telegram->sendMessage([
                'chat_id' => $this->chatId,
                'text' => '👋 Выберите действие:',
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($keyboard)
            ]);
        }
    }

    /**
     * @return void
     */
    protected function showHelp(): void
    {
        $message = "<blockquote><b>🌎 Помощь</b></blockquote>\n\n\n";
        $message .= "🤖<b> Как создать бота?</b>\n\n\n";
        $message .= "1️⃣ Открываем в телеграмме @BotFather и нажимаем start/начать\n\n";
        $message .= "2️⃣ Выбираем команду /newbot\n\n";
        $message .= "3️⃣ Вводим любое название для бота. Потом вводим никнейм бота на английском слитно, которое обязательно заканчивается на слово _bot\n";
        $message .= "4️⃣ Придёт сообщение, где после API будет находится наш токен.\n\n\n";
        $message .= "🪙 <b> Как начать продавать VPN?</b>\n\n\n";
        $message .= "1️⃣ Нажмите на кнопку <b>🤖 Мой бот</b>\n\n";
        $message .= "2️⃣ Если у вас нет бота, укажите ранее выпущенный токен и оплатите пакеты\n";
        $message .= "<i>Если бот уже добавлен, Вам останется только приобрести пакеты и начать продажи</i>\n\n\n";
        $message .= "👨🏻‍💻 По всем вопросам обращайтесь к <b>администратору</b>";

        $this->sendMessage($message);
    }

    /**
     * Показать информацию о боте
     */
    protected function showBotInfo(?int $messageId = null): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
            if (!$salesman) {
                $this->sendMessage("❌ Ошибка: продавец не найден");
                return;
            }

            if (empty($salesman->token)) {
                $salesman->state = self::STATE_WAITING_TOKEN;
                $salesman->save();

                $this->sendMessage("<b>Введите токен вашего бота:</b>\n\nТокен можно получить у @BotFather");
                return;
            }

            $message = "<blockquote>🤖 Информация о вашем боте:</blockquote>\n\n";
            $message .= "🔗 Ваш бот: $salesman->bot_link\n";
            $message .= "✅ Статус: " . ($salesman->bot_active ? "Активен" : "Отключен") . "\n\n";

            // Добавляем кнопку для перезагрузки бота
            $keyboard = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => $salesman->bot_active ? '🟢 Отключить бота' : '🔴 Включить бота',
                            'callback_data' => json_encode(['action' => 'toggle_bot'])
                        ],
//                        [
//                            'text' => '📁 Купить пакеты',
//                            'callback_data' => json_encode(['action' => 'buy_packs'])
//                        ],
                    ],
                    [
                        [
                            'text' => '♻️ Привязать нового бота',
                            'callback_data' => json_encode(['action' => 'change_bot'])
                        ]
                    ],
                    [
                        [
                            'text' => '🔄 Перезагрузить бота',
                            'callback_data' => json_encode(['action' => 'reload_bot'])
                        ]
                    ]
                ]
            ];

            if ($messageId) {
                $this->editMessage($message, $keyboard, $messageId);
            } else {
                $this->sendMessage($message, $keyboard);
            }
        } catch (\Exception $e) {
            Log::error('Show bot info error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Перезагружает бота, обновляя вебхук
     */
    private function reloadBot(): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
            if (!$salesman) {
                $this->sendMessage("❌ Ошибка: продавец не найден");
                return;
            }

            if (empty($salesman->token)) {
                $this->sendMessage("❌ Ошибка: токен бота не установлен");
                return;
            }

            // Создаем экземпляр API для бота продавца
            $salesmanBot = new Api($salesman->token);

            // Устанавливаем вебхук для бота продавца
            $webhookUrl = rtrim(self::WEBHOOK_BASE_URL, '/') . '/api/telegram/salesman-bot/' . $salesman->token . '/init';
            $salesmanBot->setWebhook(['url' => $webhookUrl]);

            $this->sendMessage("✅ Бот успешно перезагружен, Webhook обновлен.");
        } catch (\Exception $e) {
            Log::error('Bot reload error: ' . $e->getMessage());
            $this->sendMessage("❌ Ошибка при перезагрузке бота: " . $e->getMessage());
        }
    }

    /**
     * Toggle bot active status
     */
    private function toggleBot(int $messageId): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
            if (!$salesman) {
                $this->sendMessage("❌ Ошибка: продавец не найден");
                return;
            }

            $salesman->bot_active = !$salesman->bot_active;
            $salesman->save();

            $this->showBotInfo($messageId);

            $status = $salesman->bot_active ? "включен 🟢" : "отключен 🔴";
//            $this->sendMessage("✅ Бот успешно " . $status);
        } catch (Exception $e) {
            Log::error('Toggle bot error: ' . $e->getMessage());
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

            // Получаем количество активных пакетов
            $activePacks = PackSalesman::where('salesman_id', $salesman->id)
                ->where('status', PackSalesman::PAID)
                ->count();

            $userUsername = $salesman->username ?? 'Не указано';

            // Формируем сообщение с информацией о пользователе
            $message = "<blockquote><b>🪪 Личный кабинет</b></blockquote>\n\n";
            $message .= "🆔 <b>Telegram ID: <code>{$salesman->telegram_id}</code></b>\n";

            if ($userUsername !== 'Не указано') {
                $message .= "📟 <b>Имя:</b> <code>{$userUsername}</code>\n";
            }

            $message .= "📦 <b>Активных пакетов: <code>{$activePacks}</code></b>\n";

            if ($salesman->created_at) {
                $message .= "📅 <b>Регистрация: <code>" . $salesman->created_at->format('d.m.Y H:i') . "</code></b>\n";
            }

            $keyboard = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => '🔑 Войти в личный кабинет',
                            'url' => $this->generateAuthUrl()
                        ]
                    ]
                ]
            ];

            $this->sendMessage($message, $keyboard);
        } catch (\Exception $e) {
            Log::error('Show profile error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }
}
