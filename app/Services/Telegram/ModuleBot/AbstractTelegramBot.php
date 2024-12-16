<?php

namespace App\Services\Telegram\ModuleBot;

use App\Repositories\KeyActivate\KeyActivateRepository;
use App\Repositories\PackSalesman\PackSalesmanRepository;
use App\Repositories\Salesman\SalesmanRepository;
use App\Services\Pack\PackSalesmanService;
use App\Services\Salesman\SalesmanService;
use Exception;
use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\Update;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Support\Facades\Log;

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
    protected const WEBHOOK_BASE_URL = 'https://vpn-telegram.com/';
    protected const BOT_TYPE_FATHER = 'father';
    protected const BOT_TYPE_SALESMAN = 'salesman';

    /**
     * @throws TelegramSDKException
     */
    public function __construct(string $token)
    {
        $this->packSalesmanService = app(PackSalesmanService::class);
        $this->salesmanService = app(SalesmanService::class);
        $this->keyActivateRepository = app(KeyActivateRepository::class);
        $this->packSalesmanRepository = app(PackSalesmanRepository::class);
        $this->salesmanRepository = app(SalesmanRepository::class);

        if (empty($token)) {
            throw new \RuntimeException('Telegram bot token not configured');
        }
        $this->telegram = new Api($token);
    }


    /**
     * Инициализация бота
     */
    public function init(): void
    {
        try {
            $this->update = $this->telegram->getWebhookUpdate();
            $this->chatId = $this->update->getChat()->id;
            $this->username = $this->update->getChat()->username;
            $this->firstName = $this->update->getChat()->firstName;

            Log::debug('USER STATE: ' . $this->update);
            $this->processUpdate();
        } catch (Exception $e) {
            Log::error(static::class . ' initialization error: ' . $e->getMessage());
            $this->sendErrorMessage();
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
                "father-bot/{$token}/init" :
                "salesman-bot/{$token}/init";

            $webhookUrl = self::WEBHOOK_BASE_URL . $path;
            Log::debug('Setting webhook URL: ' . $webhookUrl);

            $response = $this->telegram->setWebhookWithoutCertificate(['url' => $webhookUrl]);
            return (bool)$response;
        } catch (Exception $e) {
            Log::error('Webhook setting error: ' . $e->getMessage());
            return false;
        }
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
     * Меню бота
     */
    abstract protected function generateMenu(): void;

    /**
     * Helper отправки сообщения
     *
     * @param string $text
     * @param mixed $keyboard
     */
    protected function sendMessage(string $text, $keyboard = null): void
    {
        try {
            $params = [
                'chat_id' => $this->chatId,
                'text' => $text,
                'parse_mode' => 'HTML'
            ];

            if ($keyboard) {
                $params['reply_markup'] = $keyboard;
            }

            $this->telegram->sendMessage($params);
        } catch (Exception $e) {
            Log::error('Send message error: ' . $e->getMessage());
        }
    }

    /**
     * Базовый error
     */
    protected function sendErrorMessage(): void
    {
        $this->sendMessage('Произошла ошибка. Пожалуйста, попробуйте позже или обратитесь к администратору.');
    }

    /**
     * Создает и отправляет меню с кнопками
     * @param array $buttons Массив кнопок в формате [['text' => 'Button Text', 'callback_data' => 'action'], ...]
     * @param string $message Сообщение над меню
     * @param array $options Дополнительные опции для клавиатуры
     */
    protected function sendMenu(array $buttons, string $message, array $options = []): void
    {
        $keyboard = Keyboard::make();

        // Применяем базовые настройки клавиатуры
        $keyboard->inline()
            ->setResizeKeyboard($options['resize'] ?? true)
            ->setOneTimeKeyboard($options['one_time'] ?? false);

        // Группируем кнопки по 2 в ряд (если не указано иное)
        $buttonsPerRow = $options['buttons_per_row'] ?? 2;
        $rows = array_chunk($buttons, $buttonsPerRow);

        foreach ($rows as $row) {
            $buttonRow = [];
            foreach ($row as $button) {
                $buttonRow[] = Keyboard::inlineButton([
                    'text' => $button['text'],
                    'callback_data' => $button['callback_data']
                ]);
            }
            $keyboard->row(...$buttonRow);
        }

        $this->sendMessage($message, $keyboard);
    }
}
