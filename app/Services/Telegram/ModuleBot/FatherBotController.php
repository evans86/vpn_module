<?php

namespace App\Services\Telegram\ModuleBot;

use App\Models\KeyActivate\KeyActivate;
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
                    case '🤖 Мой бот':
                        $this->showBotInfo();
                        break;
                    case '📦 Пакеты':
                        $this->showPacksList();
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
                case 'show_packs':
                    $this->showPacksList();
                    break;
                case 'toggle_bot':
                    $this->toggleBot($messageId);
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

            $this->sendMessage("<b>🔄 Введите токен нового бота:</b>\n\nТокен можно получить в @BotFather\n\n⚠️ Внимание: после смены бота все старые ссылки перестанут работать!");
        } catch (Exception $e) {
            Log::error('Initiate bot change error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Показать список пакетов продавца
     */
    private function showPacksList(): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
            if (!$salesman) {
                $this->sendMessage("❌ Ошибка: продавец не найден");
                return;
            }

            $packs = PackSalesman::where('salesman_id', $salesman->id)
                ->where('status', PackSalesman::PAID)
                ->with('pack')
                ->get();

            if ($packs->isEmpty()) {
                $this->sendMessage("❌ У вас нет активных пакетов");
                return;
            }

            $message = "<b>📦 Ваши пакеты доступов:</b>\n\n";
            $keyboard = ['inline_keyboard' => []];

            foreach ($packs as $packSalesman) {
                $pack = $packSalesman->pack;
                $keyboard['inline_keyboard'][] = [
                    [
                        'text' => "📦 ID: {$packSalesman->id}",
                        'callback_data' => json_encode([
                            'action' => 'show_pack',
                            'pack_id' => $packSalesman->id
                        ])
                    ]
                ];
            }

            $this->sendMessage($message, $keyboard);
        } catch (\Exception $e) {
            Log::error('Error in showPacksList: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Показать детали пакета и его ключи
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

            // Основная информация о пакете
            $message = "<b>📦 Информация о пакете:</b>\n\n";
            $message .= "💾 Трафик: " . number_format($pack->traffic_limit / (1024 * 1024 * 1024), 1) . " GB\n";
            $message .= "⏱ Период: {$pack->period} дней\n\n";

            // Добавляем ключи активации
            $message .= "<b>🔑 Ключи активации:</b>\n";
            foreach ($keys as $index => $key) {
                $status = $key->user_tg_id ? "✅ Активирован" : "⚪️ Не активирован";
                if ($key->user_tg_id) {
                    $message .= ($index + 1) . ". <code>{$key->id}</code> - {$status} (ID: {$key->user_tg_id})\n";
                } else {
                    $message .= ($index + 1) . ". <code>{$key->id}</code> - {$status}\n";
                }
            }

            // Кнопка для выгрузки ключей в .txt файл
            $keyboard = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => '📥 Выгрузить ключи в .txt файл',
                            'callback_data' => json_encode([
                                'action' => 'export_keys',
                                'pack_id' => $packSalesmanId
                            ])
                        ]
                    ]
                ]
            ];

            // Проверяем длину сообщения
            if (strlen($message) <= 4096) {
                // Если сообщение не превышает лимит, отправляем всё одним сообщением
                $this->sendMessage($message, $keyboard);
            } else {
                // Если сообщение слишком длинное, разбиваем на части
                $this->sendMessage("<b>📦 Информация о пакете:</b>\n\n💾 Трафик: " . number_format($pack->traffic_limit / (1024 * 1024 * 1024), 1) . " GB\n⏱ Период: {$pack->period} дней\n\n");

                // Отправляем ключи частями
                $chunkSize = 50; // Количество ключей в одном сообщении
                $keyChunks = $keys->chunk($chunkSize);
                $globalIndex = 1; // Глобальный счетчик для сквозной нумерации
                foreach ($keyChunks as $index => $chunk) {
                    $keyMessage = "<b>🔑 Ключи активации (часть " . ($index + 1) . "):</b>\n";
                    foreach ($chunk as $key) {
                        $status = $key->user_tg_id ? "✅ Активирован" : "⚪️ Не активирован";
                        if ($key->user_tg_id) {
                            $keyMessage .= $globalIndex . ". <code>{$key->id}</code> - {$status} (ID: {$key->user_tg_id})\n";
                        } else {
                            $keyMessage .= $globalIndex . ". <code>{$key->id}</code> - {$status}\n";
                        }
                        $globalIndex++; // Увеличиваем глобальный счетчик
                    }
                    // Отправляем часть ключей
                    $this->sendMessage($keyMessage);
                }
                // Отправляем кнопку после всех ключей
                $this->sendMessage("Вы можете выгрузить все ключи в .txt файл:", $keyboard);
            }
        } catch (\Exception $e) {
            Log::error('Error in showPackDetails: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Экспорт ключей в текстовый файл
     */
    private function exportKeysToFile(int $packSalesmanId): void
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
            $content = "Пакет: ID {$packSalesman->id}\n";
            $content .= "Трафик: " . number_format($pack->traffic_limit / (1024 * 1024 * 1024), 1) . " GB\n";
            $content .= "Период: {$pack->period} дней\n";
            $content .= "Ключи можно активировать в боте: $salesman->bot_link\n\n";
            $content .= "Ключи активации:\n";

            foreach ($keys as $index => $key) {
                $status = $key->user_tg_id ? "Активирован" : "Не активирован";
//                if ($key->user_tg_id) {
//                    $content .= ($index + 1) . ". {$key->id} - {$status} (ID пользователя: {$key->user_tg_id})\n";
//                } else {
                $content .= ($index + 1) . ". {$key->id} - {$status}\n";
//                }
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
                'caption' => "📥 Выгрузка ключей для пакета {$pack->id}"
            ]);

            // Удаляем временный файл
            unlink($tempPath);
        } catch (\Exception $e) {
            Log::error('Error in exportKeysToFile: ' . $e->getMessage());
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

            $message = "👋 *Добро пожаловать в систему управления доступами VPN*\n\n";
            $message .= "🔸 Покупайте пакеты ключей\n";
            $message .= "🔸 Привяжите своего бота для активации доступов\n";
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
    protected function generateMenu($message = null): void
    {
        $keyboard = [
            'keyboard' => [
                [
                    ['text' => '🤖 Мой бот'],
                    ['text' => '📦 Пакеты']
                ],
                [
                    ['text' => '👤 Профиль'],
                    ['text' => '❓ Помощь']
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
                'reply_markup' => json_encode($keyboard)
            ]);
        }
    }

    /**
     * @return void
     */
    protected function showHelp(): void
    {
        $message = "*❓ Помощь*\n\n";
        $message .= "🔹 *Создание бота:*\n";
        $message .= "1. Создайте бота в @BotFather\n";
        $message .= "2. Получите токен бота\n";
        $message .= "3. Нажмите '🤖 Мой бот' и отправьте токен\n\n";
        $message .= "🔹 *Продажа доступов:*\n";
        $message .= "1. Привяжите своего бота\n";
        $message .= "2. Начните продавать доступы через своего бота\n\n";
//        $message .= "По всем вопросам обращайтесь в @BOTT_SUPPORT_BOT";

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

            $message = "<b>🤖 Информация о вашем боте:</b>\n\n";
            $message .= "🔗 Ваш бот: $salesman->bot_link\n";
            $message .= "✅ Статус: " . ($salesman->bot_active ? "Активен" : "Отключен") . "\n\n";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => $salesman->bot_active ? '🔴 Отключить бота' : '🟢 Включить бота',
                            'callback_data' => json_encode(['action' => 'toggle_bot'])
                        ]
                    ],
                    [
                        [
                            'text' => '🔄 Привязать нового бота',
                            'callback_data' => json_encode(['action' => 'change_bot'])
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
