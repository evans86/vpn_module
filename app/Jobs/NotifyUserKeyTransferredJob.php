<?php

namespace App\Jobs;

use App\Helpers\UrlHelper;
use App\Models\KeyActivate\KeyActivate;
use App\Services\Notification\TelegramNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Уведомление в Telegram о переносе ключа на другой сервер (ставится в очередь при массовом переносе / balance).
 * При одиночном переносе вызывается синхронно из MarzbanService::transferUser с тем же текстом через ::telegramMessage().
 */
class NotifyUserKeyTransferredJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $keyActivateId;

    public $timeout = 120;

    public $tries = 5;

    /** @var array<int, int> */
    public $backoff = [15, 60, 180, 300];

    public function __construct(string $keyActivateId)
    {
        $this->keyActivateId = $keyActivateId;
    }

    /**
     * Как в TelegramNotificationService::sendToUser — без бота продавца/модуля отправлять некуда.
     */
    public static function canEnqueueForOwner(KeyActivate $key): bool
    {
        if (!$key->user_tg_id) {
            return false;
        }

        return $key->module_salesman_id !== null || $key->pack_salesman_id !== null;
    }

    public static function telegramMessage(string $keyActivateId): string
    {
        $message = '⚠️ Ваш ключ доступа: '
            . "<code>{$keyActivateId}</code> был перемещен на новый сервер!\n\n";
        $message .= "🔗 Для продолжения работы:\n";
        $message .= "• Заново вставьте ссылку-подключение в клиент VPN, или\n";
        $message .= "• При выключенном VPN нажмите кнопку обновления конфигурации\n\n";
        $message .= "\n" . UrlHelper::telegramConfigLinksHtml($keyActivateId);

        return $message;
    }

    public function handle(TelegramNotificationService $telegram): void
    {
        $key = KeyActivate::with(['moduleSalesman.botModule', 'packSalesman.salesman'])
            ->find($this->keyActivateId);
        if (!$key instanceof KeyActivate) {
            Log::warning('NotifyUserKeyTransferredJob: ключ не найден', ['key_id' => $this->keyActivateId]);

            return;
        }
        if (!self::canEnqueueForOwner($key)) {
            return;
        }

        $message = self::telegramMessage($this->keyActivateId);

        try {
            $telegram->sendToUser($key, $message);
        } catch (\Throwable $e) {
            Log::error('NotifyUserKeyTransferredJob: ошибка отправки в Telegram', [
                'error' => $e->getMessage(),
                'key_id' => $this->keyActivateId,
                'source' => 'mass_transfer_notification',
            ]);
            throw $e;
        }
    }
}
