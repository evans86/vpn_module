<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Установка Marzban в фоне через очередь (queue:work-safe / cron schedule), без удержания HTTP.
 * Используется при QUEUE_CONNECTION != sync (основной путь в проде).
 */
class InstallMarzbanPanelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Установка по SSH может идти сильно дольше таймаута веба. */
    public $timeout = 7200;

    public $tries = 1;

    /** @var int */
    private $serverId;

    public function __construct(int $serverId)
    {
        $this->serverId = $serverId;
    }

    public function handle(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $code = (int) Artisan::call('panel:install-marzban', ['server_id' => (string) $this->serverId]);
        $output = trim((string) Artisan::output());

        if ($code !== 0) {
            Log::error('InstallMarzbanPanelJob: artisan panel:install-marzban failed', [
                'server_id' => $this->serverId,
                'exit_code' => $code,
                'output' => substr($output, 0, 4000),
            ]);

            throw new RuntimeException(
                'panel:install-marzban завершился с кодом '.$code.($output !== '' ? ': '.Str::limit($output, 500) : '')
            );
        }

        Log::info('InstallMarzbanPanelJob: успешно', ['server_id' => $this->serverId]);
    }
}
