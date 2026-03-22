<?php

namespace App\Services\Telegram\ModuleBot;

use App\Models\KeyActivate\KeyActivate;
use App\Models\Order\Order;
use App\Models\OrderSetting\OrderSetting;
use App\Models\PackSalesman\PackSalesman;
use App\Helpers\UrlHelper;
use App\Models\Salesman\Salesman;
use App\Services\Order\OrderService;
use App\Services\Panel\PanelStrategy;
use Exception;
use Illuminate\Support\Facades\Cache;
use Telegram\Bot\Api;
use Illuminate\Support\Facades\Log;

class FatherBotController extends AbstractTelegramBot
{
    private const STATE_WAITING_TOKEN = 'waiting_token';

    private const STATE_WAITING_HELP_TEXT = 'waiting_help_text';

    private const STATE_WAITING_PAYMENT_PROOF = 'waiting_payment_proof';

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
                    'from' => $callbackQuery->getFrom()->getId(),
                    'source' => 'telegram'
                ]);
                $this->processCallback($callbackQuery->getData());
                return;
            }

            if ($message) {
                $text = $message->getText();
                
                // Проверяем состояние ожидания подтверждения оплаты ПЕРЕД проверкой текста
                // так как фото может быть отправлено без текста
                $salesman = Salesman::where('telegram_id', $this->chatId)->first();
                
                if ($salesman && $salesman->state === self::STATE_WAITING_PAYMENT_PROOF) {
                    // Проверяем, есть ли фото в сообщении
                    $photo = $message->getPhoto();
                    
                    // Преобразуем коллекцию в массив, если нужно
                    if ($photo && method_exists($photo, 'toArray')) {
                        $photo = $photo->toArray();
                    } elseif ($photo && is_object($photo) && method_exists($photo, 'all')) {
                        $photo = $photo->all();
                    }
                    
                    Log::info('Checking payment proof state', [
                        'salesman_id' => $salesman->id,
                        'has_photo' => !empty($photo),
                        'photo_type' => gettype($photo),
                        'photo_count' => is_array($photo) ? count($photo) : (is_countable($photo) ? count($photo) : 0),
                        'has_text' => !empty($text),
                        'message_has_photo' => $message->has('photo'),
                        'source' => 'telegram'
                    ]);
                    
                    if ($photo && (is_array($photo) || is_object($photo)) && !empty($photo)) {
                        // Преобразуем в массив для обработки
                        $photoArray = is_array($photo) ? $photo : (method_exists($photo, 'toArray') ? $photo->toArray() : [$photo]);
                        
                        Log::info('Processing payment proof photo', [
                            'salesman_id' => $salesman->id,
                            'photo_sizes' => count($photoArray),
                            'source' => 'telegram'
                        ]);
                        $this->handlePaymentProof($photoArray);
                        return;
                    } else {
                        // Если нет фото, но есть текст - возможно пользователь хочет отменить
                        if ($text && (str_contains(strtolower($text), 'отмен') || str_contains(strtolower($text), 'cancel'))) {
                            // Пытаемся найти активный заказ
                            $orderId = Cache::get("order_pending_proof:{$salesman->id}");
                            if ($orderId) {
                                Log::info('Cancelling order from text command', [
                                    'order_id' => $orderId,
                                    'salesman_id' => $salesman->id,
                                    'source' => 'telegram'
                                ]);
                                $this->cancelOrder($orderId);
                                return;
                            }
                        }
                        $this->sendMessage('❌ Пожалуйста, отправьте скриншот подтверждения оплаты (фото).');
                        return;
                    }
                }

                if (!$text) {
                    Log::warning('Received message without text', [
                        'message' => $message,
                        'source' => 'telegram'
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
                    // Нормализуем ключ перед обработкой
                    $normalizedKey = $this->normalizeKeyText($text);
                    $this->handleKeyInfoRequest($normalizedKey);
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
                    case '🛒 Купить пакет':
                        $this->showBuyPackMenu();
                        break;
                    case '🪪 Личный кабинет':
                        $this->showProfile();
                        break;
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
                'trace' => $e->getTraceAsString(),
                'source' => 'telegram'
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
            Log::info('Processing callback data', ['data' => $data, 'source' => 'telegram']);
            $params = json_decode($data, true);
            if (!$params || !isset($params['action'])) {
                Log::error('Invalid callback data', ['data' => $data, 'source' => 'telegram']);
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
                    // Удаляем сообщение при нажатии "Отмена"
                    $this->deleteMessage();
                    $this->answerCallbackQuery();
                    break;

                case 'buy_pack':
                    if (isset($params['pack_id'])) {
                        $this->showPaymentMethods($params['pack_id']);
                    }
                    break;

                case 'select_payment':
                    if (isset($params['pack_id']) && isset($params['payment_id'])) {
                        $this->createOrder($params['pack_id'], $params['payment_id']);
                    }
                    break;

                case 'cancel_order':
                    if (isset($params['order_id'])) {
                        Log::info('Cancel order callback received', [
                            'order_id' => $params['order_id'],
                            'salesman_id' => $this->chatId,
                            'source' => 'telegram'
                        ]);
                        $this->cancelOrder($params['order_id']);
                    } else {
                        Log::warning('Cancel order callback without order_id', [
                            'params' => $params,
                            'source' => 'telegram'
                        ]);
                        $this->sendMessage('❌ Ошибка: не указан ID заказа.');
                    }
                    break;

                default:
                    Log::warning('Unknown callback action', [
                        'action' => $params['action'],
                        'data' => $data,
                        'source' => 'telegram'
                    ]);
            }
            // Всегда отвечаем на callback query чтобы убрать "loading"
            $this->answerCallbackQuery();

        } catch (Exception $e) {
            Log::error('Process callback error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'source' => 'telegram',
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
            Log::error('Error answering callback query: ' . $e->getMessage(), ['source' => 'telegram']);
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
            if (!$botDeepLink) {
                $this->sendMessage("❌ Ошибка: имя бота не настроено. Обратитесь к администратору.");
                return;
            }
            $hash = explode('auth_', $botDeepLink)[1];

            // Сохраняем в кэше информацию о том, что запрос идет из бота
            Cache::put("telegram_auth:{$hash}", [
                'user_id' => $this->chatId,
                'callback_url' => UrlHelper::personalRoute('personal.auth.telegram.callback'),
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
            Log::error('Auth initiation failed: ' . $e->getMessage(), ['source' => 'telegram']);
            $this->sendMessage("❌ Ошибка: не удалось сформировать ссылку для входа");
        }
    }

    /**
     * Генерация URL для авторизации
     *
     * @return string|null
     */
    public function generateAuthUrl(): ?string
    {
        try {
            if (!$this->chatId) {
                Log::warning('Chat ID not available, cannot generate auth URL', ['source' => 'telegram']);
                return null;
            }

            // Пытаемся получить username из env
            $botUsername = ltrim(env('TELEGRAM_FATHER_BOT_NAME'), '@');
            
            // Если не настроен в env, получаем через API (с кэшированием)
            if (empty($botUsername)) {
                $botUsername = Cache::remember('telegram_father_bot_username', 3600, function () {
                    try {
                        $botInfo = $this->telegram->getMe();
                        return $botInfo->getUsername();
                    } catch (\Exception $e) {
                        Log::error('Failed to get bot username from API: ' . $e->getMessage(), ['source' => 'telegram']);
                        return null;
                    }
                });
            }

            if (empty($botUsername)) {
                Log::warning('Bot username not available, cannot generate auth URL', ['source' => 'telegram']);
                return null;
            }

            $hash = bin2hex(random_bytes(16));
            Cache::put("telegram_auth:{$hash}", [
                'user_id' => $this->chatId,
                'callback_url' => UrlHelper::personalRoute('personal.auth.telegram.callback'),
            ], now()->addMinutes(5));

            return "https://t.me/{$botUsername}?start=auth_{$hash}";
        } catch (\Exception $e) {
            Log::error('Error generating auth URL: ' . $e->getMessage(), [
                'source' => 'telegram',
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
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
            Log::error('Auth processing failed: ' . $e->getMessage(), ['source' => 'telegram']);
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
                Log::warning('Missing required field in Telegram auth data', ['field' => $field, 'source' => 'telegram']);
                return null;
            }
        }

        // 2. Проверка временной метки (не старше 1 дня)
        $authDate = (int)$data['auth_date'];
        if (time() - $authDate > 86400) { // 24 часа
            Log::warning('Expired Telegram auth data', ['auth_date' => $authDate, 'source' => 'telegram']);
            return null;
        }

        // 3. Верификация хэша (если есть все необходимые данные)
        if (!$this->verifyTelegramHash($data)) {
            Log::warning('Invalid Telegram hash verification', ['source' => 'telegram']);
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
                Log::error('Failed to get subscription info for key ' . $key->id . ': ' . $e->getMessage(), ['source' => 'telegram']);
                $info = ['used_traffic' => null];
            }

            // Даты
            if ($key->created_at && !is_null($key->created_at)) {
                $message .= "📅 <b>Создан:</b> " . $key->created_at->format('d.m.Y H:i') . "\n";
            }

            if ($key->finish_at && !is_null($key->finish_at)) {
                $message .= "⏳ <b>Действует до:</b> " . date('d.m.Y', $key->finish_at) . "\n";
                $message .= "⏳ <b>Осталось дней:</b> " . ceil(($key->finish_at - time()) / (60 * 60 * 24)) . "\n";
            }

            // Трафик
            if ($key->traffic_limit) {
                $trafficUsedGB = round($info['used_traffic'] / (1024 * 1024 * 1024), 2);

                $message .= "📶 <b>Трафик:</b>\n";
                $message .= "   • Использовано: {$trafficUsedGB} GB\n";
            }

            $this->sendMessage($message);

        } catch (Exception $e) {
            Log::error('Key info request error: ' . $e->getMessage(), ['source' => 'telegram']);
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
            Log::error('Initiate help text change error: ' . $e->getMessage(), ['source' => 'telegram']);
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
            Log::error('Help text update error: ' . $e->getMessage(), ['source' => 'telegram']);
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
            Log::error('Reset help text error: ' . $e->getMessage(), ['source' => 'telegram']);
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
            Log::error('Initiate bot change error: ' . $e->getMessage(), ['source' => 'telegram']);
            $this->sendErrorMessage();
        }
    }

    /**
     * Показать список пакетов продавца
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

            // Оптимизация: получаем пакеты один раз с нужными отношениями
            $packs = PackSalesman::where('salesman_id', $salesman->id)
                ->where('status', PackSalesman::PAID)
                ->with(['pack', 'keyActivates'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Общая статистика по ВСЕМ пакетам (используем уже загруженные данные)
            $totalKeys = 0;
            $usedKeys = 0;

            foreach ($packs as $packSalesman) {
                $totalKeys += $packSalesman->keyActivates->count();
                $usedKeys += $packSalesman->keyActivates->whereNotNull('user_tg_id')->count();
            }
            $activeKeys = $totalKeys - $usedKeys;

            // Пагинация для отображения (используем коллекцию)
            $packsPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $packs->forPage($page, $perPage),
                $packs->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );

            if ($packsPaginated->isEmpty()) {
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
            $currentPage = $packsPaginated->currentPage();
            $lastPage = $packsPaginated->lastPage();
            $totalPacks = $packsPaginated->total();

            $message .= "📦 <i>Пакеты на странице {$currentPage}/{$lastPage} (всего: {$totalPacks}):</i>\n\n";

            $keyboard = ['inline_keyboard' => []];

            // Добавляем пакеты с красивым оформлением
            foreach ($packsPaginated->items() as $packSalesman) {
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
            if ($packsPaginated->hasPages()) {
                $paginationButtons = [];

                // Текущая страница и общее количество
                $currentPage = $packsPaginated->currentPage();
                $lastPage = $packsPaginated->lastPage();

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
                if ($packsPaginated->hasMorePages()) {
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

            if ($messageId) {
                $this->editMessage($message, $keyboard, $messageId);
            } else {
                $this->sendMessage($message, $keyboard);
            }
        } catch (\Exception $e) {
            Log::error('Error in showPacksList: ' . $e->getMessage(), ['source' => 'telegram']);
            $this->sendErrorMessage();
        }
    }

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
            Log::error('Error in showPackDetails: ' . $e->getMessage(), ['source' => 'telegram']);
            $this->sendErrorMessage();
        }
    }

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
            Log::error('Error in exportKeysToFile: ' . $e->getMessage(), ['source' => 'telegram']);
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
                if ($pack) {
                    $content .= "Период: {$pack->period} дней\n";
                } else {
                    $content .= "Тип: Архивный пакет\n";
                }
                $content .= "Ключи можно активировать в боте: $salesman->bot_link\n\n";
                $content .= "Не активированные ключи активации:\n";
            }

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
            Log::error('Error in exportUnactivatedKeysToFile: ' . $e->getMessage(), ['source' => 'telegram']);
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
            Log::error('Error in exportKeysWithTrafficToFile: ' . $e->getMessage(), ['source' => 'telegram']);
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
            Log::error('Error in exportUsedKeysToFile: ' . $e->getMessage(), ['source' => 'telegram']);
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
                $webhookUrl = rtrim(self::webhookBaseUrl(), '/') . '/api/telegram/salesman-bot/' . $token . '/init';
                $salesmanBot->setWebhook(['url' => $webhookUrl]);

                $message = "✅ Бот успешно добавлен!\n\nТеперь вы можете купить пакет VPN-доступов.";
                $this->generateMenu($message);
            }
        } catch (\Exception $e) {
            Log::error('Bot token validation error: ' . $e->getMessage(), ['source' => 'telegram']);
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
            Log::error('Start command error: ' . $e->getMessage(), ['source' => 'telegram']);
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
                    ['text' => '🌎 Помощь']
                ]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];

        // Добавляем кнопку "Купить пакет" если система включена
        if (OrderSetting::isSystemEnabled()) {
            $keyboard['keyboard'][] = [['text' => '🛒 Купить пакет']];
        }

        $keyboard['keyboard'][] = [
            ['text' => '✏️ Изменить текст "❓ Помощь"'],
            ['text' => '🔄 Сбросить текст "❓ Помощь"']
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
            Log::error('Show bot info error: ' . $e->getMessage(), ['source' => 'telegram']);
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
            $webhookUrl = rtrim(self::webhookBaseUrl(), '/') . '/api/telegram/salesman-bot/' . $salesman->token . '/init';
            $salesmanBot->setWebhook(['url' => $webhookUrl]);

            $this->sendMessage("✅ Бот успешно перезагружен, Webhook обновлен.");
        } catch (\Exception $e) {
            Log::error('Bot reload error: ' . $e->getMessage(), ['source' => 'telegram']);
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

        } catch (Exception $e) {
            Log::error('Toggle bot error: ' . $e->getMessage(), ['source' => 'telegram']);
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

            // Пытаемся сгенерировать URL для входа в личный кабинет
            $authUrl = $this->generateAuthUrl();
            $keyboard = null;
            
            if ($authUrl) {
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            [
                                'text' => '🔑 Войти в личный кабинет',
                                'url' => $authUrl
                            ]
                        ]
                    ]
                ];
            }

            $this->sendMessage($message, $keyboard);
        } catch (\Exception $e) {
            Log::error('Show profile error: ' . $e->getMessage(), ['source' => 'telegram']);
            $this->sendErrorMessage();
        }
    }

    /**
     * Показать меню покупки пакетов
     */
    protected function showBuyPackMenu(): void
    {
        try {
            if (!OrderSetting::isSystemEnabled()) {
                $this->sendMessage('❌ Система покупки пакетов временно отключена.');
                return;
            }

            $orderService = new OrderService(app(\App\Services\Pack\PackSalesmanService::class));
            $availablePacks = $orderService->getAvailablePacks();

            if ($availablePacks->isEmpty()) {
                $this->sendMessage('❌ В данный момент нет доступных пакетов для покупки.');
                return;
            }

            $message = "🛒 <b>Доступные пакеты для покупки:</b>\n\n";

            $buttons = [];
            foreach ($availablePacks as $pack) {
                $message .= "📦 <b>{$pack->title}</b>\n";
                $message .= "💰 Цена: " . number_format($pack->price, 0, '.', ' ') . " ₽\n";
                $message .= "🔑 Ключей: {$pack->count}\n";
                $message .= "⏱ Период: {$pack->period} дней\n\n";

                $buttons[] = [
                    [
                        'text' => "📦 {$pack->title} - " . number_format($pack->price, 0, '.', ' ') . " ₽",
                        'callback_data' => json_encode(['action' => 'buy_pack', 'pack_id' => $pack->id])
                    ]
                ];
            }

            $buttons[] = [
                [
                    'text' => '❌ Отмена',
                    'callback_data' => json_encode(['action' => 'current_page'])
                ]
            ];

            $this->sendMessage($message, [
                'inline_keyboard' => $buttons
            ]);
        } catch (\Exception $e) {
            Log::error('Show buy pack menu error: ' . $e->getMessage(), ['source' => 'telegram']);
            $this->sendErrorMessage();
        }
    }

    /**
     * Показать способы оплаты для выбранного пакета
     */
    protected function showPaymentMethods(int $packId): void
    {
        try {
            $orderService = new OrderService(app(\App\Services\Pack\PackSalesmanService::class));
            $pack = \App\Models\Pack\Pack::findOrFail($packId);
            $paymentMethods = $orderService->getActivePaymentMethods();

            if ($paymentMethods->isEmpty()) {
                $this->sendMessage('❌ В данный момент нет доступных способов оплаты.');
                return;
            }

            $message = "💳 <b>Выберите способ оплаты:</b>\n\n";
            $message .= "📦 Пакет: <b>{$pack->title}</b>\n";
            $message .= "💰 Сумма к оплате: <b>" . number_format($pack->price, 0, '.', ' ') . " ₽</b>\n\n";

            $buttons = [];
            foreach ($paymentMethods as $method) {
                $icon = $method->getTypeIcon();
                $buttons[] = [
                    [
                        'text' => "{$icon} {$method->name}",
                        'callback_data' => json_encode(['action' => 'select_payment', 'pack_id' => $packId, 'payment_id' => $method->id])
                    ]
                ];
            }

            $buttons[] = [
                [
                    'text' => '❌ Отмена',
                    'callback_data' => json_encode(['action' => 'current_page'])
                ]
            ];

            $this->sendMessage($message, [
                'inline_keyboard' => $buttons
            ]);
        } catch (\Exception $e) {
            Log::error('Show payment methods error: ' . $e->getMessage(), ['source' => 'telegram']);
            $this->sendErrorMessage();
        }
    }

    /**
     * Создать заказ
     */
    protected function createOrder(int $packId, int $paymentMethodId): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->firstOrFail();
            $orderService = new OrderService(app(\App\Services\Pack\PackSalesmanService::class));

            $order = $orderService->create($packId, $salesman->id, $paymentMethodId);
            $order->load(['pack', 'paymentMethod']);

            $message = "✅ <b>Заказ создан!</b>\n\n";
            $message .= "🆔 ID заказа: <b>#{$order->id}</b>\n";
            $message .= "📦 Пакет: <b>{$order->pack->title}</b>\n";
            $message .= "💰 Сумма: <b>" . number_format($order->amount, 0, '.', ' ') . " ₽</b>\n";
            $message .= "💳 Способ оплаты: <b>{$order->paymentMethod->name}</b>\n\n";

            $message .= "📋 <b>Реквизиты для оплаты:</b>\n";
            $message .= "{$order->paymentMethod->details}\n\n";

            if ($order->paymentMethod->instructions) {
                $message .= "ℹ️ <b>Инструкция:</b>\n";
                $message .= "{$order->paymentMethod->instructions}\n\n";
            }

            $message .= "📸 После оплаты отправьте скриншот подтверждения оплаты (фото чека или перевода).";

            // Устанавливаем состояние ожидания подтверждения оплаты
            $salesman->state = self::STATE_WAITING_PAYMENT_PROOF;
            $salesman->save();

            // Сохраняем ID заказа в кэше для связи с фото
            Cache::put("order_pending_proof:{$salesman->id}", $order->id, now()->addHours(24));

            $buttons = [
                [
                    [
                        'text' => '❌ Отменить заказ',
                        'callback_data' => json_encode(['action' => 'cancel_order', 'order_id' => $order->id])
                    ]
                ]
            ];

            $this->sendMessage($message, [
                'inline_keyboard' => $buttons
            ]);
        } catch (\Exception $e) {
            Log::error('Create order error: ' . $e->getMessage(), ['source' => 'telegram']);
            $this->sendMessage('❌ Ошибка при создании заказа: ' . $e->getMessage());
        }
    }

    /**
     * Обработать подтверждение оплаты (фото)
     */
    protected function handlePaymentProof(array $photo): void
    {
        try {
            Log::info('handlePaymentProof called', [
                'chat_id' => $this->chatId,
                'photo_count' => count($photo),
                'source' => 'telegram'
            ]);
            
            $salesman = Salesman::where('telegram_id', $this->chatId)->firstOrFail();
            $orderId = Cache::get("order_pending_proof:{$salesman->id}");

            Log::info('Payment proof order lookup', [
                'salesman_id' => $salesman->id,
                'order_id' => $orderId,
                'source' => 'telegram'
            ]);

            if (!$orderId) {
                Log::warning('No active order found for payment proof', [
                    'salesman_id' => $salesman->id,
                    'source' => 'telegram'
                ]);
                $this->sendMessage('❌ Не найден активный заказ. Пожалуйста, создайте новый заказ.');
                $salesman->state = null;
                $salesman->save();
                return;
            }

            // Получаем файл с наибольшим размером (лучшее качество)
            $largestPhoto = null;
            $largestSize = 0;
            foreach ($photo as $photoSize) {
                // Проверяем, что это объект PhotoSize
                if (is_object($photoSize) && method_exists($photoSize, 'getFileSize')) {
                    $size = $photoSize->getFileSize() ?? 0;
                    if ($size > $largestSize) {
                        $largestSize = $size;
                        $largestPhoto = $photoSize;
                    }
                } elseif (is_array($photoSize) && isset($photoSize['file_size'])) {
                    // Если это массив
                    $size = $photoSize['file_size'] ?? 0;
                    if ($size > $largestSize) {
                        $largestSize = $size;
                        $largestPhoto = $photoSize;
                    }
                }
            }

            if (!$largestPhoto) {
                $this->sendMessage('❌ Не удалось обработать фото. Попробуйте отправить еще раз.');
                return;
            }

            // Получаем file_id
            $fileId = null;
            if (is_object($largestPhoto) && method_exists($largestPhoto, 'getFileId')) {
                $fileId = $largestPhoto->getFileId();
            } elseif (is_array($largestPhoto) && isset($largestPhoto['file_id'])) {
                $fileId = $largestPhoto['file_id'];
            }

            if (!$fileId) {
                $this->sendMessage('❌ Не удалось получить ID файла. Попробуйте отправить еще раз.');
                return;
            }

            // Скачиваем файл через Telegram Bot API
            $file = $this->telegram->getFile(['file_id' => $fileId]);
            $filePath = $file->getFilePath();

            // Скачиваем файл
            $fileUrl = "https://api.telegram.org/file/bot" . config('telegram.father_bot.token') . "/{$filePath}";
            $fileContent = file_get_contents($fileUrl);

            // Сохраняем файл
            $storagePath = storage_path('app/public/order_proofs');
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            $fileName = "order_{$orderId}_" . time() . ".jpg";
            $fullPath = "{$storagePath}/{$fileName}";
            file_put_contents($fullPath, $fileContent);

            // Сохраняем путь относительно storage/app/public
            $relativePath = "order_proofs/{$fileName}";

            // Обновляем заказ
            $orderService = new OrderService(app(\App\Services\Pack\PackSalesmanService::class));
            $orderService->submitPaymentProof($orderId, $relativePath);

            // Сбрасываем состояние
            $salesman->state = null;
            $salesman->save();
            Cache::forget("order_pending_proof:{$salesman->id}");

            $message = "✅ <b>Подтверждение оплаты отправлено!</b>\n\n";
            $message .= "🆔 ID заказа: <b>#{$orderId}</b>\n\n";
            $message .= "⏳ Ваш заказ отправлен на проверку администратору.\n";
            $message .= "Вы получите уведомление после проверки.";

            $this->sendMessage($message);
        } catch (\Exception $e) {
            Log::error('Handle payment proof error: ' . $e->getMessage(), ['source' => 'telegram']);
            $this->sendMessage('❌ Ошибка при обработке подтверждения оплаты: ' . $e->getMessage());
        }
    }

    /**
     * Отменить заказ
     */
    protected function cancelOrder(int $orderId): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->firstOrFail();
            $order = Order::where('id', $orderId)
                ->where('salesman_id', $salesman->id)
                ->firstOrFail();

            if (!$order->canBeCancelled()) {
                $this->sendMessage('❌ Этот заказ нельзя отменить.');
                return;
            }

            // Удаляем сообщение с заказом перед отменой
            $this->deleteMessage();

            $orderService = new OrderService(app(\App\Services\Pack\PackSalesmanService::class));
            $orderService->cancel($orderId);

            // Сбрасываем состояние если было
            if ($salesman->state === self::STATE_WAITING_PAYMENT_PROOF) {
                $salesman->state = null;
                $salesman->save();
                Cache::forget("order_pending_proof:{$salesman->id}");
            }

            $this->sendMessage("✅ Заказ #{$orderId} отменен.");
        } catch (\Exception $e) {
            Log::error('Cancel order error: ' . $e->getMessage(), ['source' => 'telegram']);
            $this->sendMessage('❌ Ошибка при отмене заказа: ' . $e->getMessage());
        }
    }
}
