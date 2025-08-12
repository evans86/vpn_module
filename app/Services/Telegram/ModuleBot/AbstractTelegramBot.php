<?php

namespace App\Services\Telegram\ModuleBot;

use App\Repositories\KeyActivate\KeyActivateRepository;
use App\Repositories\PackSalesman\PackSalesmanRepository;
use App\Repositories\Salesman\SalesmanRepository;
use App\Services\Key\KeyActivateService;
use App\Services\Pack\PackSalesmanService;
use App\Services\Salesman\SalesmanService;
use Exception;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\Update;
use Telegram\Bot\Exceptions\TelegramSDKException;

abstract class AbstractTelegramBot
{
    protected Api $telegram;
    protected ?Update $update = null;
    protected ?int $chatId = null;
    protected ?string $username = null;
    protected ?string $firstName = null;
    protected PackSalesmanService $packSalesmanService;
    protected SalesmanService $salesmanService;
    protected KeyActivateRepository $keyActivateRepository;
    protected PackSalesmanRepository $packSalesmanRepository;
    protected SalesmanRepository $salesmanRepository;
    protected KeyActivateService $keyActivateService;
    protected const WEBHOOK_BASE_URL = 'https://vpn-telegram.com/';
    protected const BOT_TYPE_FATHER = 'father';
    protected const BOT_TYPE_SALESMAN = 'salesman';

    /**
     * @throws TelegramSDKException
     */
    public function __construct(string $token)
    {
        try {
            $this->packSalesmanService = app(PackSalesmanService::class);
            $this->salesmanService = app(SalesmanService::class);
            $this->keyActivateService = app(KeyActivateService::class);
            $this->keyActivateRepository = app(KeyActivateRepository::class);
            $this->packSalesmanRepository = app(PackSalesmanRepository::class);
            $this->salesmanRepository = app(SalesmanRepository::class);

            $this->telegram = new Api($token);
        } catch (Exception $e) {
            Log::error('Error initializing Telegram bot', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Инициализация бота
     * @throws Exception
     */
    public function init(): void
    {
        try {
            Log::info('Initializing bot', [
                'update_raw' => request()->all()
            ]);

            // Получаем update через Telegram SDK
            $this->update = $this->telegram->getWebhookUpdate();

            // Получаем chat_id
            if ($this->update->getMessage()) {
                $this->chatId = $this->update->getMessage()->getChat()->getId();
                $this->username = $this->update->getMessage()->getFrom()->getUsername();
                $this->firstName = $this->update->getMessage()->getFrom()->getFirstName();
            } elseif ($this->update->getCallbackQuery()) {
                $this->chatId = $this->update->getCallbackQuery()->getMessage()->getChat()->getId();
                $this->username = $this->update->getCallbackQuery()->getFrom()->getUsername();
                $this->firstName = $this->update->getCallbackQuery()->getFrom()->getFirstName();
            }

            Log::info('Bot initialized', [
                'chat_id' => $this->chatId,
                'username' => $this->username,
                'first_name' => $this->firstName
            ]);

            $this->processUpdate();
        } catch (Exception $e) {
            Log::info('!Error initializing bot!', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Установка webhook
     *
     * @param string $token
     * @param string $botType
     * @return bool
     */
    protected function setWebhook(string $token, string $botType = self::BOT_TYPE_SALESMAN): bool
    {
        try {
            $path = $botType === self::BOT_TYPE_FATHER ?
                "api/telegram/father-bot/{$token}/init" :
                "api/telegram/salesman-bot/{$token}/init";

            $webhookUrl = rtrim(self::WEBHOOK_BASE_URL, '/') . '/' . $path;

            Log::info('Setting webhook URL', [
                'url' => $webhookUrl,
                'bot_type' => $botType,
                'token' => substr($token, 0, 10) . '...'
            ]);

            // Добавляем задержку, чтобы избежать Too Many Requests
            sleep(1);

            $response = $this->telegram->setWebhook(['url' => $webhookUrl]);

            Log::info('Webhook set response', [
                'response' => $response,
                'bot_type' => $botType
            ]);

            return (bool)$response;
        } catch (Exception $e) {
            Log::error('Webhook setting error', [
                'error' => $e->getMessage(),
                'bot_type' => $botType,
                'token' => substr($token, 0, 10) . '...',
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    protected function verifyTelegramHash(array $data): bool
    {
        // 1. Извлечение хэша из данных
        $hash = $data['hash'];
        unset($data['hash']);

        // 2. Сортировка данных по алфавиту
        ksort($data);

        // 3. Формирование строки данных
        $dataCheckArr = [];
        foreach ($data as $key => $value) {
            $dataCheckArr[] = $key . '=' . $value;
        }
        $dataCheckString = implode("\n", $dataCheckArr);

        // 4. Генерация секретного ключа
        $secretKey = hash('sha256', env('TELEGRAM_FATHER_BOT_TOKEN'), true);

        // 5. Генерация хэша
        $generatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        // 6. Сравнение хэшей
        return hash_equals($generatedHash, $hash);
    }

    /**
     * обработка update
     */
    abstract protected function processUpdate(): void;

    /**
     * обработка команды start
     */
    abstract protected function start(): void;

    /**
     * Helper отправки сообщения
     *
     * @param string $text
     * @param mixed $keyboard
     */
    public function sendMessage(string $text, $keyboard = null): void
    {
        try {
            $params = [
                'chat_id' => $this->chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true
            ];

            if ($keyboard !== null) {
                if (is_array($keyboard)) {
                    if (isset($keyboard['reply_markup'])) {
                        $params['reply_markup'] = $keyboard['reply_markup'];
                    } else {
                        $params['reply_markup'] = json_encode($keyboard);
                    }
                } elseif ($keyboard instanceof Keyboard) {
                    $params['reply_markup'] = json_encode($keyboard->toArray());
                }
            }

            $this->telegram->sendMessage($params);
        } catch (Exception $e) {
            Log::error('Send message error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'params' => $params ?? null
            ]);
        }
    }

    /**
     * Редактирование сообщения
     *
     * @param string $text
     * @param mixed $keyboard
     * @param int|null $messageId
     */
    public function editMessage(string $text, $keyboard = null, ?int $messageId = null): void
    {
        try {
            $params = [
                'chat_id' => $this->chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'HTML'
            ];

            if ($keyboard !== null) {
                if (is_array($keyboard)) {
                    if (isset($keyboard['reply_markup'])) {
                        $params['reply_markup'] = $keyboard['reply_markup'];
                    } else {
                        $params['reply_markup'] = json_encode($keyboard);
                    }
                } elseif ($keyboard instanceof Keyboard) {
                    $params['reply_markup'] = json_encode($keyboard->toArray());
                }
            }

            $this->telegram->editMessageText($params);
        } catch (Exception $e) {
            Log::error('Edit message error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'params' => $params ?? null
            ]);
        }
    }

    /**
     * Базовый error
     */
    protected function sendErrorMessage(): void
    {
        $this->sendMessage('Произошла ошибка. Пожалуйста, попробуйте позже или обратитесь к администратору.');
    }

    protected function isValidKeyFormat(string $text): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $text) === 1;
    }
}
