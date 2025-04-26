<?php

namespace App\Console\Commands;

use App\Models\KeyActivate\KeyActivate;
use App\Services\Key\KeyActivateService;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NotifyExpiringKeysCommand extends Command
{
    protected $signature = 'notify:expiring-keys';
    protected $description = 'Send notifications about expiring keys';

    private KeyActivateService $keyActivateService;
    private NotificationService $notificationService;

    public function __construct(
        KeyActivateService  $keyActivateService,
        NotificationService $notificationService
    )
    {
        parent::__construct();
        $this->keyActivateService = $keyActivateService;
        $this->notificationService = $notificationService;
    }

    public function handle()
    {
        try {
            $this->notifyUnactivatedKeys();
            $this->notifyExpiringActiveKeys();
        } catch (\Exception $e) {
            Log::error('Error in NotifyExpiringKeysCommand', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Уведомление об истечении срока для не активированных ключей
     *
     * @return void
     */
    private function notifyUnactivatedKeys()
    {
        // Получаем не активированные ключи, у которых срок активации истекает через 2 дня
        $keys = KeyActivate::where('status', KeyActivate::PAID)
            ->where('finish_at', '=', null)
            ->where('deleted_at', '>', now()->timestamp)
            ->where('deleted_at', '<=', now()->timestamp + 172800)
            ->get()
            ->groupBy('pack_salesman_id');

        echo $keys->count();

        foreach ($keys as $packSalesmanId => $packKeys) {
            $this->notificationService->sendExpiringKeysNotification(
                $packSalesmanId,
                $packKeys->count(),
                $packKeys->first()->deleted_at
            );
        }
    }

    /**
     * Уведомление об истечении срока для активированных ключей
     *
     * @return void
     */
    private function notifyExpiringActiveKeys()
    {
        // Получаем активированные ключи, у которых срок работы истекает через 3 дня
        $keys = KeyActivate::where('status', KeyActivate::ACTIVE)
            ->where('deleted_at', '=', null)
            ->where('finish_at', '>', now()->timestamp)
            ->where('finish_at', '<=', now()->timestamp + 172800)
            ->get();

        echo $keys->count();

        foreach ($keys as $key) {
            $this->notificationService->sendKeyExpirationNotification(
                $key->user_tg_id,
                $key->id,
                $key->finish_at
            );
        }
    }
}
