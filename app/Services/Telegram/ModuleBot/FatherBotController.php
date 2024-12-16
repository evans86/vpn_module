<?php

namespace App\Services\Telegram\ModuleBot;

use App\Dto\Salesman\SalesmanFactory;
use App\Models\Pack\Pack;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Salesman\Salesman;
use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;

class FatherBotController extends AbstractTelegramBot
{
    private const STATE_WAITING_TOKEN = 'waiting_token';
    private const STATE_WAITING_PAYMENT = 'waiting_payment';

    private ?string $userState = null;

    public function __construct(string $token)
    {
        parent::__construct($token);
        $this->setWebhook($token, self::BOT_TYPE_FATHER);
    }

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

            // Проверяем состояние ожидания токена
            if ($this->userState === self::STATE_WAITING_TOKEN && $message) {
                $this->handleBotToken($message->text);
                return;
            }

            // Обработка выбора пакета
            if ($this->userState === self::STATE_WAITING_PAYMENT && $this->update->callbackQuery) {
                $this->processCallback($this->update->callbackQuery->data);
                return;
            }

            if ($message) {
                $text = $message->text;
                switch ($text) {
                    case '🛍 Купить пакет':
                        $this->showPacksList();
                        break;
                    case '🤖 Мой бот':
                        $this->showBotInfo();
                        break;
                    case '👤 Профиль':
                        $this->showProfile();
                        break;
                    case '❓ Помощь':
                        $this->actionHelp();
                        break;
                }
            }
        } catch (\Exception $e) {
            Log::error('Error processing update: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    /**
     * Показать список пакетов
     */
    private function showPacksList(): void
    {
        try {
            $packs = Pack::where('active', true)->get();
            if ($packs->isEmpty()) {
                $this->sendMessage('❌ В данный момент нет доступных пакетов');
                return;
            }

            $message = "📦 *Доступные пакеты:*\n\n";
            $keyboard = Keyboard::make()->inline();

            foreach ($packs as $pack) {
                $message .= "🔸 *{$pack->name}*\n";
                $message .= "💰 Цена: {$pack->price} руб.\n";
                $message .= "📝 Описание: {$pack->description}\n\n";

                $keyboard->row(
                    Keyboard::inlineButton([
                        'text' => "Купить {$pack->name} за {$pack->price} руб.",
                        'callback_data' => "buy?id={$pack->id}"
                    ])
                );
            }

            $this->userState = self::STATE_WAITING_PAYMENT;
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
                $salesman->token = self::STATE_WAITING_TOKEN;
                $salesman->save();

                $this->userState = self::STATE_WAITING_TOKEN;
                $this->sendMessage('Отправьте токен вашего бота:');
                return;
            }

            $message = "🤖 *Информация о вашем боте*\n\n";
            $message .= "🔗 Ссылка на бота: {$salesman->bot_link}\n";
            $message .= "✅ Статус: Активен\n\n";
            $message .= "Чтобы привязать другого бота, отправьте новый токен.";

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
                ->where('active', true)
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
     * Handle bot token from user
     */
    private function handleBotToken(string $token): void
    {
        try {
            $salesman = Salesman::where('telegram_id', $this->chatId)->firstOrFail();

            // Устанавливаем webhook для бота продавца
            if (!$this->setWebhook($token, self::BOT_TYPE_SALESMAN)) {
                $this->sendMessage('❌ Ошибка при настройке бота. Пожалуйста, проверьте токен и попробуйте снова.');
                return;
            }

            $salesmanDto = SalesmanFactory::fromEntity($salesman);
            $salesmanDto->token = $token;
            $salesmanDto->bot_link = $this->getBotLinkFromToken($token);

            $this->salesmanService->updateToken($salesmanDto);

            $this->userState = null;
            $this->sendMessage("✅ Бот успешно привязан!\nСсылка на бота: {$salesmanDto->bot_link}");
            $this->generateMenu();
        } catch (\Exception $e) {
            Log::error('Bot token handling error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
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
     * Start command handler
     */
    protected function start(): void
    {
        try {
            // Проверяем существование пользователя
            $existingSalesman = Salesman::where('telegram_id', $this->chatId)->first();
            Log::debug('existingSalesman: ' . $this->chatId);
            Log::debug('existingSalesman: ' . $this->username);

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
     * Generate menu
     */
    protected function generateMenu(): void
    {
        $buttons = [
            [
                'text' => '🛍 Купить пакет'
            ],
            [
                'text' => '🤖 Мой бот'
            ],
            [
                'text' => '👤 Профиль'
            ],
            [
                'text' => '❓ Помощь'
            ]
        ];

        $message = "👋 *Добро пожаловать в систему управления доступами VPN*\n\n";
        $message .= "🔸 Покупайте пакеты ключей\n";
        $message .= "🔸 Создавайте своего бота\n";
        $message .= "🔸 Продавайте VPN доступы\n";

        $this->sendMenu($buttons, $message);
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

        $this->sendMessage($message);
    }

    /**
     * Get bot link from token
     * @param string $token
     * @return string
     */
    private function getBotLinkFromToken(string $token): string
    {
        try {
            $telegram = new Api($token);
            $botInfo = $telegram->getMe();
            return '@' . $botInfo->username;
        } catch (\Exception $e) {
            Log::error('Error getting bot info: ' . $e->getMessage());
            // Возвращаем запасной вариант, если не удалось получить информацию о боте
            $botName = explode(':', $token)[0];
            return '@bot' . $botName;
        }
    }
}
