<?php

namespace Tests\Unit\Services\Telegram\ModuleBot;

use App\Services\Telegram\ModuleBot\AbstractTelegramBot;
use Exception;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Tests\TestCase;
use Mockery;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Message;

/**
 * Тестовый класс для тестирования абстрактного класса Telegram бота
 *
 * Этот класс тестирует базовую функциональность Telegram бота:
 * - Отправку сообщений (с клавиатурой и без)
 * - Установку и обработку webhook
 * - Обработку ошибок при отправке сообщений
 * - Отправку сообщений об ошибках
 */
class TestTelegramBot extends AbstractTelegramBot
{
    /**
     * Конструктор тестового класса
     *
     * @param string $token Токен Telegram бота
     * @throws TelegramSDKException
     */
    public function __construct(string $token)
    {
        parent::__construct($token);
    }

    /**
     * Публичный метод для тестирования отправки сообщений
     *
     * @param string $text Текст сообщения
     * @param array|null $keyboard Опциональная клавиатура
     */
    public function publicSendMessage(string $text, array $keyboard = null): void
    {
        $this->sendMessage($text, $keyboard);
    }

    /**
     * Публичный метод для тестирования установки webhook
     *
     * @param string $token Токен бота
     * @param string $botType Тип бота (по умолчанию salesman)
     * @return bool Результат установки webhook
     */
    public function publicSetWebhook(string $token, string $botType = self::BOT_TYPE_SALESMAN): bool
    {
        return $this->setWebhook($token, $botType);
    }

    /**
     * Публичный метод для тестирования отправки сообщений об ошибках
     */
    public function publicSendErrorMessage(): void
    {
        $this->sendErrorMessage();
    }

    /**
     * Пустая реализация абстрактного метода для обработки обновлений
     */
    protected function processUpdate(): void
    {
    }

    /**
     * Пустая реализация абстрактного метода для обработки команды start
     */
    protected function start(): void
    {
    }

    /**
     * Устанавливает protected свойства для тестирования
     *
     * @param int $chatId ID чата для тестирования
     */
    public function setTestProperties(int $chatId): void
    {
        $this->chatId = $chatId;
    }

    /**
     * Устанавливает экземпляр Telegram API для тестирования
     *
     * @param Api $telegram Мок объекта Telegram API
     */
    public function setTelegramApi(Api $telegram): void
    {
        $this->telegram = $telegram;
    }
}

/**
 * Тесты для абстрактного класса Telegram бота
 *
 * Тестируем основные функции бота:
 * 1. Отправка сообщений (с клавиатурой и без)
 * 2. Установка webhook
 * 3. Обработка ошибок
 * 4. Отправка сообщений об ошибках
 */
class AbstractTelegramBotTest extends TestCase
{
    protected TestTelegramBot $bot;
    protected string $token = 'test_token';
    protected string $baseUrl = 'https://api.telegram.org/bot';
    protected Api $telegram;

    /**
     * Подготовка окружения для каждого теста
     *
     * Создаем:
     * - Мок Telegram API
     * - Экземпляр тестового бота
     *
     * @throws TelegramSDKException
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Мокаем Telegram API
        $this->telegram = Mockery::mock(Api::class);

        // Создаем экземпляр тестового класса
        $this->bot = new TestTelegramBot($this->token);
        $this->bot->setTelegramApi($this->telegram);
    }

    /**
     * Очистка после каждого теста
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Создает мок объекта Message с заданными параметрами
     *
     * Мокируем основные методы объекта Message:
     * - messageId(): ID сообщения для отслеживания
     * - text(): текст отправленного сообщения
     * - chat->id(): ID чата, куда отправлено сообщение
     *
     * @param int $chatId ID чата для ответа
     * @param string $text Текст сообщения
     * @return Message Мок объекта сообщения
     */
    protected function createMessageMock(int $chatId, string $text): Message
    {
        $message = Mockery::mock(Message::class);
        $message->allows('messageId')->andReturns(1);
        $message->allows('text')->andReturns($text);
        $message->allows('chat->id')->andReturns($chatId);
        return $message;
    }

    /**
     * @test
     * Проверяем отправку простого текстового сообщения
     */
    public function it_can_send_message()
    {
        $chatId = 123456;
        $text = 'Test message';

        // Устанавливаем chatId
        $this->bot->setTestProperties($chatId);

        $message = $this->createMessageMock($chatId, $text);

        // Ожидаем вызов sendMessage у Telegram API
        $this->telegram->expects('sendMessage')
            ->once()
            ->with([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML'
            ])
            ->andReturn($message);

        // Вызываем метод через публичный wrapper
        $this->bot->publicSendMessage($text);

        // Проверяем, что метод был вызван
        $this->assertTrue(true);
        Mockery::close();
    }

    /**
     * @test
     * Проверяем отправку сообщения с клавиатурой
     */
    public function it_can_send_message_with_keyboard()
    {
        $chatId = 123456;
        $text = 'Test message';
        $keyboard = [
            'keyboard' => [
                [['text' => 'Button 1'], ['text' => 'Button 2']]
            ],
            'resize_keyboard' => true
        ];

        $this->bot->setTestProperties($chatId);

        $message = $this->createMessageMock($chatId, $text);

        $this->telegram->expects('sendMessage')
            ->once()
            ->with([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($keyboard)
            ])
            ->andReturn($message);

        // Вызываем метод через публичный wrapper
        $this->bot->publicSendMessage($text, $keyboard);

        // Проверяем, что метод был вызван
        $this->assertTrue(true);
        Mockery::close();
    }

    /**
     * @test
     * Проверяем обработку ошибок при отправке сообщения
     */
    public function it_handles_send_message_error()
    {
        $chatId = 123456;
        $text = 'Test message';

        $this->bot->setTestProperties($chatId);

        $this->telegram->expects('sendMessage')
            ->once()
            ->andThrow(new Exception('API Error'));

        // Не должно выбрасывать исключение
        $this->bot->publicSendMessage($text);
        $this->assertTrue(true); //тест дошел до этой точки
        Mockery::close();
    }

    /**
     * @test
     * Проверяем установку webhook
     */
    public function it_can_set_webhook()
    {
        $botType = 'salesman';

        $this->telegram->expects('setWebhook')
            ->once()
            ->with(Mockery::on(function ($params) {
                return isset($params['url']) &&
                    strpos($params['url'], 'api/telegram/salesman-bot') !== false;
            }))
            ->andReturn(true);

        $result = $this->bot->publicSetWebhook($this->token, $botType);

        $this->assertTrue($result);
        Mockery::close();
    }

    /**
     * @test
     * Проверяем обработку ошибок при установке webhook
     */
    public function it_handles_webhook_error()
    {
        $botType = 'salesman';

        $this->telegram->expects('setWebhook')
            ->once()
            ->andThrow(new Exception('Webhook error'));

        $result = $this->bot->publicSetWebhook($this->token, $botType);

        $this->assertFalse($result);
        Mockery::close();
    }

    /**
     * @test
     * Проверяем отправку сообщения об ошибке
     */
    public function it_can_send_error_message()
    {
        $chatId = 123456;
        $errorMessage = 'Произошла ошибка. Пожалуйста, попробуйте позже или обратитесь к администратору.';

        $this->bot->setTestProperties($chatId);

        $message = $this->createMessageMock($chatId, $errorMessage);

        $this->telegram->expects('sendMessage')
            ->once()
            ->with([
                'chat_id' => $chatId,
                'text' => $errorMessage,
                'parse_mode' => 'HTML'
            ])
            ->andReturn($message);

        $this->bot->publicSendErrorMessage();

        // Проверяем, что метод был вызван
        $this->assertTrue(true);
        Mockery::close();
    }
}
