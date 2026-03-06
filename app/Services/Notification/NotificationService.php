<?php

namespace App\Services\Notification;

use App\Models\KeyActivate\KeyActivate;
use App\Models\PackSalesman\PackSalesman;
use App\Repositories\KeyActivate\KeyActivateRepository;
use App\Repositories\PackSalesman\PackSalesmanRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    private PackSalesmanRepository $packSalesmanRepository;
    private KeyActivateRepository $keyActivateRepository;
    private TelegramNotificationService $telegramService;

    public function __construct(
        PackSalesmanRepository $packSalesmanRepository,
        KeyActivateRepository $keyActivateRepository,
        TelegramNotificationService $telegramService
    ) {
        $this->packSalesmanRepository = $packSalesmanRepository;
        $this->keyActivateRepository = $keyActivateRepository;
        $this->telegramService = $telegramService;
    }

    public function sendExpiringKeysNotification(int $packSalesmanId, int $keysCount, int $expirationDate)
    {
        try {
            $packSalesman = $this->packSalesmanRepository->findByIdOrFail($packSalesmanId);
            $expirationDate = date('d.m.Y', $expirationDate);

            $message = "🔔 В Вашем пакете подходит срок реализации для {$keysCount} " . self::pluralKeys($keysCount) . ".\n";
            $message .= "Необходимо активировать ключи до {$expirationDate} \n";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Информация о пакете',
                            'callback_data' => json_encode([
                                'action' => 'show_pack',
                                'pack_id' => $packSalesman->id
                            ])
                        ]
                    ]
                ]
            ];

            $this->telegramService->sendToSalesman($packSalesman->salesman, $message, $keyboard);

        } catch (\Exception $e) {
            Log::error('Error sending expiring keys notification', [
                'pack_salesman_id' => $packSalesmanId,
                'error' => $e->getMessage(),
                'source' => 'notification'
            ]);
        }
    }

    public function sendKeyExpirationNotification(int $userTgId, string $keyId, int $expirationDate)
    {
        try {
            $keyActivate = $this->keyActivateRepository->findById($keyId);
            $expirationDate = date('d.m.Y', $expirationDate);

            $message = "⚠️ Внимание! У вашего ключа <code>{$keyId}</code> заканчивается срок работы <b>{$expirationDate}</b>.\n";
            $message .= "После окончания срока работы ключ будет деактивирован.";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Открыть конфигурацию',
                            'url' => \App\Helpers\UrlHelper::configUrl($keyId)
                        ]
                    ]
                ]
            ];

            $this->telegramService->sendToUser($keyActivate, $message, $keyboard);

        } catch (\Exception $e) {
            Log::error('Error sending key expiration notification', [
                'user_tg_id' => $userTgId,
                'key_id' => $keyId,
                'error' => $e->getMessage(),
                'source' => 'notification'
            ]);
        }
    }

    public function sendKeyActivatedNotification(int $salesmanTgId, string $keyId)
    {
        try {
            $keyActivate = $this->keyActivateRepository->findById($keyId);
            $salesman = $this->getSalesmanFromKey($keyActivate);

            if (!$salesman) {
                return;
            }

            $message = "✅ Ключ <code>{$keyId}</code> был успешно активирован\n";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Открыть конфигурацию',
                            'url' => \App\Helpers\UrlHelper::configUrl($keyId)
                        ]
                    ]
                ]
            ];

            $this->telegramService->sendToSalesman($salesman, $message, $keyboard);

        } catch (\Exception $e) {
            Log::error('Error sending key activated notification', [
                'salesman_tg_id' => $salesmanTgId,
                'key_id' => $keyId,
                'error' => $e->getMessage(),
                'source' => 'notification'
            ]);
        }
    }

    public function sendKeyDeactivatedNotification(int $salesmanTgId, string $keyId)
    {
        try {
            $keyActivate = $this->keyActivateRepository->findById($keyId);
            $salesman = $this->getSalesmanFromKey($keyActivate);

            if (!$salesman) {
                return;
            }

            $message = "❌ Ключ <code>{$keyId}</code> был деактивирован.\n";

            $this->telegramService->sendToSalesman($salesman, $message);

        } catch (\Exception $e) {
            Log::error('Error sending key deactivated notification', [
                'salesman_tg_id' => $salesmanTgId,
                'key_id' => $keyId,
                'error' => $e->getMessage(),
                'source' => 'notification'
            ]);
        }
    }

    /**
     * Получение продавца из ключа
     */
    private function getSalesmanFromKey(KeyActivate $keyActivate): ?\App\Models\Salesman\Salesman
    {
        if (!is_null($keyActivate->module_salesman_id)) {
            return $keyActivate->moduleSalesman;
        } else if (!is_null($keyActivate->pack_salesman_id)) {
            return $keyActivate->packSalesman->salesman;
        }

        return null;
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
