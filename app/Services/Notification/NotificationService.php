<?php

namespace App\Services\Notification;

use App\Models\KeyActivate\KeyActivate;
use App\Models\PackSalesman\PackSalesman;
use App\Repositories\KeyActivate\KeyActivateRepository;
use App\Repositories\PackSalesman\PackSalesmanRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;

class NotificationService
{
    private PackSalesmanRepository $packSalesmanRepository;
    private KeyActivateRepository $keyActivateRepository;

    public function __construct(
        PackSalesmanRepository $packSalesmanRepository,
        KeyActivateRepository  $keyActivateRepository
    )
    {
        $this->packSalesmanRepository = $packSalesmanRepository;
        $this->keyActivateRepository = $keyActivateRepository;
    }

    public function sendExpiringKeysNotification(int $packSalesmanId, int $keysCount, int $expirationDate)
    {
        try {
            $packSalesman = $this->packSalesmanRepository->findByIdOrFail($packSalesmanId);
            $expirationDate = date('d.m.Y', $expirationDate);

            $message = "🔔 В Вашем пакете подходит срок реализации для {$keysCount} " . self::pluralKeys($keysCount) . ".\n";
            $message .= "Необходимо активировать ключи до {$expirationDate} \n";

            $keyboard['inline_keyboard'][] = [
                [
                    'text' => 'Информация о пакете',
                    'callback_data' => json_encode([
                        'action' => 'show_pack',
                        'pack_id' => $packSalesman->id
                    ])
                ]
            ];

            $this->sendTelegramMessage($packSalesman->salesman->telegram_id, $message, null, $keyboard);
        } catch (\Exception $e) {
            Log::error('Error sending expiring keys notification', [
                'pack_salesman_id' => $packSalesmanId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendKeyExpirationNotification(int $userTgId, string $keyId, int $expirationDate)
    {
        try {
            $keyActivate = $this->keyActivateRepository->findById($keyId);
            $token = $keyActivate->packSalesman->salesman->token;
            $expirationDate = date('d.m.Y', $expirationDate);

            $message = "⚠️ Внимание! У вашего ключа <code>{$keyId}</code> заканчивается срок работы <b>{$expirationDate}</b>.\n";
            $message .= "После окончания срока работы ключ будет деактивирован.\n";
            $message .= "Купить новый ключ можно у создателя бота.";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Открыть конфигурацию',
                            'url' => "https://vpn-telegram.com/config/{$keyId}"
                        ]
                    ]
                ]
            ];

            $this->sendTelegramMessage($userTgId, $message, $token, $keyboard);
        } catch (\Exception $e) {
            Log::error('Error sending key expiration notification', [
                'user_tg_id' => $userTgId,
                'key_id' => $keyId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendKeyActivatedNotification(int $salesmanTgId, string $keyId)
    {
        try {
            $message = "✅ Ключ <code>{$keyId}</code> был успешно активирован\n";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Открыть конфигурацию',
                            'url' => "https://vpn-telegram.com/config/{$keyId}"
                        ]
                    ]
                ]
            ];

            $this->sendTelegramMessage($salesmanTgId, $message, null, $keyboard);
        } catch (\Exception $e) {
            Log::error('Error sending key activated notification', [
                'salesman_tg_id' => $salesmanTgId,
                'key_id' => $keyId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendKeyDeactivatedNotification(int $salesmanTgId, string $keyId)
    {
        try {
            $message = "❌ Ключ <code>{$keyId}</code> был деактивирован.\n";

            $this->sendTelegramMessage($salesmanTgId, $message);
        } catch (\Exception $e) {
            Log::error('Error sending key deactivated notification', [
                'salesman_tg_id' => $salesmanTgId,
                'key_id' => $keyId,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function sendTelegramMessage(int $chatId, string $message, string $token = null, array $keyboard = null)
    {
        try {
            if (is_null($token)) {
                $telegram = new Api(config('telegram.father_bot.token'));
            } else {
                $telegram = new Api($token);
            }

            $params = [
                'chat_id' => $chatId,
                'text' => $message,
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

            $telegram->sendMessage($params);
        } catch (Exception $e) {
            Log::error('Ошибка при отправке сообщения через Bot', [
                'error' => $e->getMessage(),
                'salesman_id' => $chatId,
            ]);
        }
    }

    private function pluralKeys(int $number): string
    {
        $lastTwoDigits = $number % 100;
        $lastDigit = $number % 10;

        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
            return 'ключей';
        } elseif ($lastDigit === 1) {
            return 'ключ';
        } elseif ($lastDigit >= 2 && $lastDigit <= 4) {
            return 'ключей';
        } else {
            return 'ключей';
        }
    }
}
