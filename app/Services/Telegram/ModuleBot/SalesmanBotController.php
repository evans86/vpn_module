<?php

namespace App\Services\Telegram\ModuleBot;

use App\Models\KeyActivate\KeyActivate;
use App\Models\Salesman\Salesman;
use Exception;
use Illuminate\Support\Facades\Log;

class SalesmanBotController extends AbstractTelegramBot
{
    private ?Salesman $salesman = null;
    private ?KeyActivate $currentPack = null;
    private const STATE_WAITING_KEY = 'waiting_key';

    public function __construct(string $token)
    {
        parent::__construct($token);

        // Находим продавца по токену
        $this->salesman = $this->salesmanRepository->findByToken($token);
        if (!$this->salesman) {
            Log::error('Salesman not found for token: ' . substr($token, 0, 10) . '...');
            throw new \RuntimeException('Salesman not found');
        }

        Log::debug('Initialized SalesmanBotController', [
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

                // Обработка команд меню
                switch ($text) {
                    case '🔑 Активировать':
                        $this->actionActivate();
                        break;
                    case '📊 Статус':
                        $this->actionStatus();
                        break;
                    case '❓ Помощь':
                        $this->actionSupport();
                        break;
                    default:
                        // Проверяем состояние ожидания ключа
                        $salesman = Salesman::where('telegram_id', $this->chatId)->first();
                        if ($salesman && $salesman->state === self::STATE_WAITING_KEY) {
                            $this->handleKeyActivation($text);
                        } else {
                            $this->sendMessage('❌ Неизвестная команда. Воспользуйтесь меню для выбора действия.');
                        }
                }
            }
        } catch (Exception $e) {
            Log::error('Error processing update in SalesmanBot', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->sendErrorMessage();
        }
    }

    private function clearState(): void
    {
        $salesman = Salesman::where('telegram_id', $this->chatId)->first();
        if ($salesman) {
            $salesman->state = null;
            $salesman->save();
        }
    }

    /**
     * обработка callback
     *
     * @param string $data
     */
    private function processCallback(string $data): void
    {
        try {
            Log::info('Processing callback data', ['data' => $data]);

            $params = json_decode($data, true);
            if (!$params || !isset($params['action'])) {
                Log::error('Invalid callback data', ['data' => $data]);
                return;
            }

            switch ($params['action']) {
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
     * обработка start
     */
    protected function start(): void
    {
        try {
            $message = "👋 Добро пожаловать в VPN бот!\n\n";
            $message .= "🔸 Активируйте ваш VPN доступ\n";
            $message .= "🔸 Проверяйте статус подключения\n";
            $message .= "🔸 Получайте помощь в настройке\n";

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

    /**
     * Support action
     */
    private function actionSupport(): void
    {
        $text = "
            <b>Как использовать VPN:</b>\n
            1. Активируйте доступ через меню\n
            2. Следуйте инструкциям для настройки\n
            3. Проверьте статус подключения\n
            \nПо всем вопросам обращайтесь к менеджеру @support";
        $this->sendMessage($text);
    }

    /**
     * Status action
     */
    private function actionStatus(): void
    {
        try {
            $userId = $this->update->getMessage()->getFrom()->getId();

            // Находим активные ключи через репозиторий
            $this->currentPack = $this->keyActivateRepository->findActiveKeyByUserAndSalesman(
                $userId,
                $this->salesman->id
            );

            if (!$this->currentPack) {
                $this->sendMessage("У вас нет активных VPN-доступов. Для приобретения обратитесь к менеджеру @" . $this->getSalesmanUsername());
                return;
            }

            $text = "
                <b>Информация о вашем VPN-доступе:</b>\n
                ID доступа: <code>" . $this->currentPack->id . "</code>\n
                Статус: " . $this->currentPack->getStatusText() . "\n
                Дата покупки: " . $this->currentPack->created_at->format('d.m.Y') . "\n
                Действителен до: " . $this->currentPack->finish_at->format('d.m.Y') . "\n" .
                ($this->currentPack->traffic_limit ? "Остаток трафика: " . round($this->currentPack->traffic_limit / 1024 / 1024 / 1024, 2) . " GB" : "Безлимитный трафик");

            $this->sendMessage($text);
        } catch (\Exception $e) {
            Log::error('Pack info error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Activate action
     */
    private function actionActivate(): void
    {
        try {
            $userId = $this->update->getMessage()->getFrom()->getId();

            // Проверяем наличие активного ключа через репозиторий
            $existingPack = $this->keyActivateRepository->findActiveKeyByUserAndSalesman(
                $userId,
                $this->salesman->id
            );

            if ($existingPack) {
                $this->sendMessage("У вас уже есть активный VPN-доступ до {$existingPack->finish_at->format('d.m.Y')}.\nДля покупки дополнительного доступа обратитесь к менеджеру @{$this->getSalesmanUsername()}");
                return;
            }

            // Устанавливаем состояние ожидания ключа в базе данных
            $salesman = Salesman::where('telegram_id', $this->chatId)->first();
            if ($salesman) {
                $salesman->state = self::STATE_WAITING_KEY;
                $salesman->save();
            }

            $this->sendMessage("<b>Введите ключ активации:</b>");

        } catch (\Exception $e) {
            Log::error('Activation error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Handle key activation
     */
    private function handleKeyActivation(string $keyId): void
    {
        try {
            // Очищаем состояние ожидания ключа
            $this->clearState();

            // Находим ключ активации
            $key = $this->keyActivateRepository->findById($keyId);

            if (!$key) {
                $this->sendMessage("❌ Ключ активации <code>" . $keyId . "</code> не найден.");
                return;
            }

            // Проверяем статус ключа
            if (!$this->keyActivateRepository->hasCorrectStatusForActivation($key)) {
                $this->sendMessage("❌ Ключ <code>" . $keyId . "</code> не может быть активирован (неверный статус).");
                return;
            }

            // Активируем ключ
            $this->currentPack = $this->keyActivateService->activate($key, $this->chatId);

            // Отправляем инструкции по настройке
            $this->sendSetupInstructions();
        } catch (\Exception $e) {
            Log::error('Key activation error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * VPN instructions
     */
    private function sendSetupInstructions(): void
    {
        // Формируем ссылку на конфигурацию VPN
        $configUrl = config('app.url') . '/config/' . $this->currentPack->key;

        $text = "<b>🔐 Ваш VPN успешно активирован!</b>\n\n";
        $text .= "<b>📱 Инструкция по настройке:</b>\n\n";
        $text .= "1. Откройте ссылку для загрузки конфигурации:\n";
        $text .= "<code>" . $configUrl . "</code>\n\n";

        // iOS
        $text .= "🍎 <b>iOS:</b>\n";
        $text .= "1. Установите приложение WireGuard из App Store\n";
        $text .= "2. Откройте ссылку выше\n";
        $text .= "3. Нажмите 'Добавить туннель'\n\n";

        // Android
        $text .= "🤖 <b>Android:</b>\n";
        $text .= "1. Установите приложение WireGuard из Google Play\n";
        $text .= "2. Откройте ссылку выше\n";
        $text .= "3. Разрешите добавление конфигурации\n\n";

        // Windows
        $text .= "💻 <b>Windows:</b>\n";
        $text .= "1. Установите WireGuard с официального сайта\n";
        $text .= "2. Откройте ссылку выше\n";
        $text .= "3. Импортируйте конфигурацию\n\n";

        $text .= "❓ Если возникли вопросы, обратитесь к менеджеру @" . $this->getSalesmanUsername();

        $this->sendMessage($text);
    }

    /**
     * Salesman username
     * @return string
     */
    private function getSalesmanUsername(): string
    {
        return $this->salesman->username ?? 'support';
    }
}
