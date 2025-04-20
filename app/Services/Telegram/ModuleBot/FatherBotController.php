<?php

namespace App\Services\Telegram\ModuleBot;

use App\Models\PackSalesman\PackSalesman;
use App\Models\Salesman\Salesman;
use DateInterval;
use DateTime;
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
                    case '🪪 Личный кабинет':
                        $this->showProfile();
                        break;
                    case '🌎 Помощь':
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

    /**
     * Показать список пакетов продавца с пагинацией
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
            $perPage = 10;

            // Получаем пакеты с пагинацией
            $packs = PackSalesman::where('salesman_id', $salesman->id)
                ->where('status', PackSalesman::PAID)
                ->with('pack')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            if ($packs->isEmpty()) {
                $this->sendMessage("❌ Кажется, что у вас <b>нет</b> активных <b>пакетов</b>, успейте приобрести пакет ключей и начать свой бизнес!");
                return;
            }

            $message = "<blockquote><b>📦 Пакеты ключей:</b></blockquote>\n\n";
            $keyboard = ['inline_keyboard' => []];

            // Добавляем пакеты на текущую страницу
            foreach ($packs as $packSalesman) {
                $pack = $packSalesman->pack;

                // Проверяем, существует ли основной пакет
                if ($pack) {
                    $date = new DateTime($packSalesman->created_at);
                    $date->add(new DateInterval("P{$pack->period}D"));
                    $formattedDate = $date->format('d.m.Y');
                    $traffic = number_format($pack->traffic_limit / (1024 * 1024 * 1024));

                    $text = "📦{$traffic}GB| Период: {$pack->period}д| Активировать до: {$formattedDate}";

//                    $text = "📦 Трафик: " . number_format($pack->traffic_limit / (1024 * 1024 * 1024), 1) . " GB|";
//                    $text .= "Период: {$pack->period} дней|";
//                    $text .= "Активировать до: {$formattedDate}";
                } else {
                    $text = "❌ Основной тариф удален";
                }

                $keyboard['inline_keyboard'][] = [
                    [
                        'text' => $text,
                        'callback_data' => json_encode([
                            'action' => 'show_pack',
                            'pack_id' => $packSalesman->id
                        ])
                    ]
                ];
            }

            // Добавляем кнопки пагинации
            if ($packs->hasPages()) {
                $paginationButtons = [];

                // Кнопка "Назад"
                if ($packs->currentPage() > 1) {
                    $paginationButtons[] = [
                        'text' => '⬅️ Назад',
                        'callback_data' => json_encode([
                            'action' => 'packs_page',
                            'page' => $packs->currentPage() - 1
                        ])
                    ];
                }

                // Кнопка "Вперед"
                if ($packs->hasMorePages()) {
                    $paginationButtons[] = [
                        'text' => 'Вперед ➡️',
                        'callback_data' => json_encode([
                            'action' => 'packs_page',
                            'page' => $packs->currentPage() + 1
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

            if ($pack) {
                $date = new DateTime($packSalesman->created_at);
                $date->add(new DateInterval("P{$pack->period}D"));
                $formattedDate = $date->format('d.m.Y');
                // Основная информация о пакете
                $message = "<b>📦 Информация о пакете:</b>\n\n";
                $message .= "💾 Трафик: " . number_format($pack->traffic_limit / (1024 * 1024 * 1024), 1) . " GB\n";
                $message .= "⏱ Период: {$pack->period} дней\n\n";
                $message .= "⏱ Период: {$pack->period} дней\n\n";
                $message .= "⏱ Активация до: {$formattedDate}\n\n";
            } else {
                // Если пакет удален, выводим сообщение об этом
                $message = "<b>📦 Информация о пакете:</b>|❌ Основной тариф удален";
            }

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

            // Кнопки для выгрузки ключей в .txt файл
            $keyboard = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => '📥 Выгрузить все ключи',
                            'callback_data' => json_encode([
                                'action' => 'export_keys',
                                'pack_id' => $packSalesmanId
                            ])
                        ],
                        [
                            'text' => '(Без текста)',
                            'callback_data' => json_encode([
                                'action' => 'export_keys_only',
                                'pack_id' => $packSalesmanId
                            ])
                        ]
                    ],
                    [
                        [
                            'text' => '📥 Выгрузить не активированные',
                            'callback_data' => json_encode([
                                'action' => 'export_unactivated_keys',
                                'pack_id' => $packSalesmanId
                            ])
                        ],
                        [
                            'text' => '(Без текста)',
                            'callback_data' => json_encode([
                                'action' => 'export_unactivated_keys_only',
                                'pack_id' => $packSalesmanId
                            ])
                        ]
                    ],
//                    [
//                        [
//                            'text' => '📥 Выгрузить с остатком трафика',
//                            'callback_data' => json_encode([
//                                'action' => 'export_keys_with_traffic',
//                                'pack_id' => $packSalesmanId
//                            ])
//                        ],
//                        [
//                            'text' => '(Без текста)',
//                            'callback_data' => json_encode([
//                                'action' => 'export_keys_with_traffic_only',
//                                'pack_id' => $packSalesmanId
//                            ])
//                        ]
//                    ],
                    [
                        [
                            'text' => '📥 Выгрузить использованные',
                            'callback_data' => json_encode([
                                'action' => 'export_used_keys',
                                'pack_id' => $packSalesmanId
                            ])
                        ],
                        [
                            'text' => '(Без текста)',
                            'callback_data' => json_encode([
                                'action' => 'export_used_keys_only',
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
                $content .= "Трафик: " . number_format($pack->traffic_limit / (1024 * 1024 * 1024), 1) . " GB\n";
                $content .= "Период: {$pack->period} дней\n";
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
                $content .= "Трафик: " . number_format($pack->traffic_limit / (1024 * 1024 * 1024), 1) . " GB\n";
                $content .= "Период: {$pack->period} дней\n";
                $content .= "Ключи можно активировать в боте: $salesman->bot_link\n\n";
                $content .= "Не активированные ключи активации:\n";
            }

            if (!empty($keys))
                $content .= "Нет не активированных ключей";

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
                'caption' => "📥 Выгрузка не активированных ключей для пакета {$pack->id}"
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
                $content .= "Трафик: " . number_format($pack->traffic_limit / (1024 * 1024 * 1024), 1) . " GB\n";
                $content .= "Период: {$pack->period} дней\n";
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
                'caption' => "📥 Выгрузка ключей с остатком трафика для пакета {$pack->id}"
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
                $content .= "Трафик: " . number_format($pack->traffic_limit / (1024 * 1024 * 1024), 1) . " GB\n";
                $content .= "Период: {$pack->period} дней\n";
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
                'caption' => "📥 Выгрузка использованных ключей для пакета {$pack->id}"
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

            $message = "👋 <i>Добро пожаловать в систему управления VPN-доступами!</i>\n\n\n";
            $message .= "🌍 <b>Хотите зарабатывать на продаже VPN?</b> С нами это просто и удобно!\n\n\n";
            $message .= "🚀 <i><b>Что вы получите:</b></i>\n\n";
            $message .= "🔹 <i>Готовую систему</i> - покупайте пакеты ключей и создавайте своего бота за считанные минуты\n\n";
            $message .= "🔹 <i>Автоматизацию</i> - Ваш бот сам выдает доступы клиентам 24/7\n\n";
            $message .= "🔹 <i>Гибкость</i> - выбирайте тарифы, управляйте ценами и следите за балансом\n\n";
            $message .= "🔹 <i>Высокий спрос</i> - VPN нужен многим, а значит, клиентов будет достаточно!\n\n";
            $message .= "🔹 <i>Простоту подключения</i> - без сложных настроек, просто привяжите своего бота\n\n\n";
            $message .= "💼  <i><b>Как начать?</b></i>\n\n";
            $message .= "1️⃣ Купите пакет VPN-ключей\n\n";
            $message .= "2️⃣ Привяжите своего бота к системе\n\n";
            $message .= "3️⃣ Начните продавать доступы и зарабатывать\n\n\n";
            $message .= "📲 Подключайтесь и создавайте свой бизнес на продаже VPN уже сегодня!\n";
            $message .= "<b>Приятного пользования!</b>\n";

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
                ],
                [
                    ['text' => '🌎 Помощь']
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

            // Получаем информацию о пользователе через Telegram API
//            $telegramUser = $this->telegram->getChat(['chat_id' => $salesman->telegram_id]);
            $userUsername = $salesman->username ?? 'Не указано';

            // Формируем сообщение с информацией о пользователе
            $message = "<blockquote><b>🪪 Личный кабинет</b></blockquote>\n\n";
            $message .= "🆔 <b>Telegram ID: <code>{$salesman->telegram_id}</code></b>\n";

            if ($userUsername !== 'Не указано') {
                $message .= "📟 <b>Имя:</b> <code>{$userUsername}</code>\n";
            }

            // Добавляем количество активных пакетов
            $message .= "📦 <b>Активных пакетов: <code>{$activePacks}</code></b>\n";

            if ($salesman->created_at) {
                $message .= "📅 <b>Регистрация: <code>" . $salesman->created_at->format('d.m.Y H:i') . "</code></b>\n";
            }

            // Отправляем сообщение с профилем пользователя
            $this->sendMessage($message);
        } catch (\Exception $e) {
            Log::error('Show profile error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }
}
