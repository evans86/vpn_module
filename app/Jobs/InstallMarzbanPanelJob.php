<?php

namespace App\Jobs;

use App\Services\Panel\PanelStrategyFactory;
use App\Services\Panel\PanelStrategy;
use DomainException;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Долгая установка панели по SSH после того, как HTTP-ответ уже ушёл клиенту —
 * чтобы прокси (Cloudflare) не отваливался по Error 524.
 */
class InstallMarzbanPanelJob
{
    use Dispatchable, Queueable;

    /** @var int */
    private $serverId;

    /** @var string */
    private $installLockCacheKey;

    public function __construct(int $serverId, string $installLockCacheKey)
    {
        $this->serverId = $serverId;
        $this->installLockCacheKey = $installLockCacheKey;
    }

    public function handle(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        try {
            $factory = new PanelStrategyFactory();
            $panelTypes = $factory->getAvailablePanelTypes();

            if ($panelTypes === []) {
                throw new DomainException('Нет ни одного доступного типа панели (PanelStrategyFactory).');
            }

            $strategy = new PanelStrategy($panelTypes[0]);
            $strategy->create($this->serverId);

            Log::info('InstallMarzbanPanelJob: панель создана', ['server_id' => $this->serverId]);
        } catch (\Throwable $e) {
            Log::critical('InstallMarzbanPanelJob: ошибка установки', [
                'server_id' => $this->serverId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            Cache::forget($this->installLockCacheKey);
        }
    }
}
