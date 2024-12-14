<?php

namespace App\Services\Telegram\ModuleBot;

use App\Repositories\KeyActivate\KeyActivateRepository;
use App\Repositories\PackSalesman\PackSalesmanRepository;
use App\Repositories\Salesman\SalesmanRepository;
use App\Services\Pack\PackSalesmanService;
use App\Services\Salesman\SalesmanService;
use Exception;
use Telegram\Bot\Api;
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
    protected const WEBHOOK_BASE_URL = 'https://vpn-telegram.com/';
    protected PackSalesmanService $packSalesmanService;
    protected SalesmanService $salesmanService;
    protected KeyActivateRepository $keyActivateRepository;
    protected PackSalesmanRepository $packSalesmanRepository;
    protected SalesmanRepository $salesmanRepository;

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
     * @param string $path
     * @return bool
     */
    public function setWebhook(string $token, string $path): bool
    {
        try {
            $response = Telegram::setWebhook([
                'url' => self::WEBHOOK_BASE_URL . $token . '/' . $path,
//                'certificate' => storage_path('app/certificates/public_key_certificate.pub')
            ]);
            return $response->getResult();
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
}
