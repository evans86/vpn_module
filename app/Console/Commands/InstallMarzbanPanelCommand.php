<?php

namespace App\Console\Commands;

use App\Services\Panel\marzban\MarzbanService;
use App\Services\Panel\PanelStrategy;
use App\Services\Panel\PanelStrategyFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Отдельный PHP-процесс (CLI), чтобы длительный SSH+FPM/nginx не резал по таймауту родительского HTTP/FPM после ответа.
 */
class InstallMarzbanPanelCommand extends Command
{
    /** @var string */
    protected $signature = 'panel:install-marzban '
        .'{server_id : ID строки сервера (таблица server)} '
        .'{--existing-only : только сохранить panel: Marzban уже установлен на VPS, без переустановки}';

    /** @var string */
    protected $description = 'Установка Marzban по SSH и запись строки panel (фон после кнопки в админке)';

    public function handle(): int
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('max_execution_time', '0');

        $serverId = (int) $this->argument('server_id');
        $lockKey = 'marzban_panel_install_lock_'.$serverId;

        try {
            $factory = new PanelStrategyFactory();
            $panelTypes = $factory->getAvailablePanelTypes();

            if ($panelTypes === []) {
                Log::critical('panel:install-marzban: нет ни одного типа панели', ['server_id' => $serverId]);
                $this->error('Нет доступных типов панелей (PanelStrategyFactory).');

                return 1;
            }

            if ($this->option('existing-only')) {
                app(MarzbanService::class)->registerPanelRecordIfInstalled($serverId);
                $this->info('Строка panel для server_id='.$serverId.' создана по данным уже установленного Marzban.');
            } else {
                $strategy = new PanelStrategy($panelTypes[0]);
                $strategy->create($serverId);
                $this->info('Установка и запись для server_id='.$serverId.' завершены успешно.');
            }

            Log::info('panel:install-marzban: успех', ['server_id' => $serverId, 'existing_only' => (bool) $this->option('existing-only')]);

            return 0;
        } catch (\Throwable $e) {
            Log::critical('panel:install-marzban: ошибка', [
                'server_id' => $serverId,
                'existing_only' => (bool) $this->option('existing-only'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error($e->getMessage());

            return 1;
        } finally {
            Cache::forget($lockKey);
        }
    }
}
