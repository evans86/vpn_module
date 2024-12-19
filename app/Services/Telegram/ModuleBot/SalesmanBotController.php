<?php

namespace App\Services\Telegram\ModuleBot;

use App\Models\KeyActivate\KeyActivate;
use App\Models\Salesman\Salesman;
use App\Repositories\KeyActivate\KeyActivateRepository;
use App\Services\Key\KeyActivateService;
use Exception;
use Illuminate\Support\Facades\Log;

class SalesmanBotController extends AbstractTelegramBot
{
    private ?Salesman $salesman = null;

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

            if (!$message || !$message->getText()) {
                return;
            }

            $text = $message->getText();

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
                    $this->actionHelp();
                    break;
                default:
                    // Проверяем, похож ли текст на ключ
                    if ($this->isValidKeyFormat($text)) {
                        $this->handleKeyActivation($text);
                    } else {
                        $this->sendMessage('❌ Неизвестная команда. Воспользуйтесь меню для выбора действия.');
                    }
            }
        } catch (\Exception $e) {
            Log::error('Process update error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    protected function start(): void
    {
        try {
            $message = "👋 Добро пожаловать в VPN бот!\n\n";
            $message .= "🔸 Активируйте ваш VPN доступ\n";
            $message .= "🔸 Проверяйте статус подключения\n";
            $message .= "🔸 Получайте помощь в настройке";

            $this->generateMenu($message);
        } catch (\Exception $e) {
            Log::error('Start command error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

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

    private function actionActivate(): void
    {
        try {
            // Проверяем, есть ли у пользователя уже активный ключ через репозиторий
            $existingKey = $this->keyActivateRepository->findActiveKeyByUserAndSalesman(
                $this->chatId,
                $this->salesman->id
            );

            if ($existingKey) {
                $finishDate = date('d.m.Y', $existingKey->finish_at);
                $this->sendMessage("У вас уже есть активный VPN-доступ до {$finishDate}.\n\nДля покупки дополнительного доступа обратитесь к @admin");
                return;
            }

            $this->sendMessage("Пожалуйста, отправьте ваш ключ активации:");
        } catch (\Exception $e) {
            Log::error('Activate action error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    private function actionStatus(): void
    {
        try {
            $activeKey = $this->keyActivateRepository->findActiveKeyByUserAndSalesman(
                $this->chatId,
                $this->salesman->id,
                KeyActivate::ACTIVE
            );

            if (!$activeKey) {
                $this->sendMessage("У вас нет активных VPN-доступов.\n\nДля активации нажмите кнопку '🔑 Активировать' и введите ваш ключ.");
                return;
            }

            $finishDate = date('d.m.Y', $activeKey->finish_at);
            $text = "📊 *Информация о вашем VPN-доступе:*\n\n";
            $text .= "🔑 ID ключа: `{$activeKey->id}`\n";
            $text .= "📅 Действителен до: {$finishDate}\n";

            if ($activeKey->traffic_limit) {
                $trafficGB = round($activeKey->traffic_limit / (1024 * 1024 * 1024), 2);
                $text .= "📊 Лимит трафика: {$trafficGB} GB\n";
            }

            $this->sendMessage($text);
        } catch (\Exception $e) {
            Log::error('Status action error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    private function actionHelp(): void
    {
        $text = "*❓ Помощь*\n\n";
        $text .= "🔹 *Активация VPN:*\n";
        $text .= "1. Нажмите '🔑 Активировать'\n";
        $text .= "2. Введите полученный ключ\n";
        $text .= "3. Следуйте инструкциям по настройке\n\n";
        $text .= "🔹 *Проверка статуса:*\n";
        $text .= "1. Нажмите '📊 Статус'\n";
        $text .= "2. Просмотрите информацию о вашем доступе\n\n";
        $text .= "По всем вопросам обращайтесь к @admin";

        $this->sendMessage($text);
    }

    private function handleKeyActivation(string $keyId): void
    {
        try {
            $key = $this->keyActivateRepository->findById($keyId);
            // Активируем ключ через сервис
            $result = $this->keyActivateService->activate($key, $this->chatId);

            if ($result) {
                $this->sendSuccessActivation($key);
            } else {
                $this->sendMessage("❌ Произошла ошибка при активации ключа.\n\nПожалуйста, попробуйте позже или обратитесь к @admin");
            }
        } catch (\Exception $e) {
            Log::error('Key activation error: ' . $e->getMessage());
            $this->sendErrorMessage();
        }
    }

    private function sendSuccessActivation(KeyActivate $key): void
    {
        $finishDate = date('d.m.Y', $key->finish_at);

        $text = "✅ VPN успешно активирован!\n\n";
        $text .= "📅 Срок действия: до {$finishDate}\n\n";
        $text .= "📱 Инструкция по настройке:\n";
        $text .= "1. Скачайте приложение Hiddify:\n";
        $text .= "Android: https://play.google.com/store/apps/details?id=org.outline.android.client\n";
        $text .= "iOS: https://apps.apple.com/us/app/outline-app/id1356177741\n\n";
        $text .= "2. Откройте ссылку:\n";
        $text .= "<code>Надо добавить ссылку на ключ</code>\n\n";
        $text .= "❓ Если возникли вопросы, обратитесь к @admin";

        $this->sendMessage($text);
    }

    private function isValidKeyFormat(string $text): bool
    {
        // Здесь можно добавить проверку формата ключа
        // Например, если это UUID или определенный формат
        return strlen($text) === 36; // Пример для UUID
    }
}
