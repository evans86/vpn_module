<?php

namespace App\Jobs;

use App\Http\Controllers\VpnConfigController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Синхронизация ссылок ключа с Marzban в БД (как фон после «Обновить» на странице конфига).
 * Ставится в очередь после успешной выдачи URL подписки VPN-клиенту.
 */
class SyncVpnKeyActivateFromPanelsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var string */
    protected $keyActivateId;

    /** @var int */
    public $timeout;

    public function __construct(string $keyActivateId)
    {
        $this->keyActivateId = $keyActivateId;
        $limit = (int) config('panel.vpn_config_refresh_time_limit', 300);
        $this->timeout = max(120, $limit);
    }

    /**
     * @param  \App\Http\Controllers\VpnConfigController  $vpnConfigController
     */
    public function handle(VpnConfigController $vpnConfigController)
    {
        $vpnConfigController->syncMarzbanForKeyActivateAfterResponse($this->keyActivateId);
    }

    /**
     * @param  \Throwable  $e
     */
    public function failed($e): void
    {
        Log::warning('SyncVpnKeyActivateFromPanelsJob failed', [
            'key_activate_id' => $this->keyActivateId,
            'error' => $e->getMessage(),
            'source' => 'vpn',
        ]);
    }
}
