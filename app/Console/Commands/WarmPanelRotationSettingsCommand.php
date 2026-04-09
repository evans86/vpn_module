<?php

namespace App\Console\Commands;

use App\Repositories\Panel\PanelRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Прогрев кэша страницы «Настройки распределения» (тяжёлый compareAllStrategies — только из cron/CLI).
 */
class WarmPanelRotationSettingsCommand extends Command
{
    protected $signature = 'panel:warm-rotation-settings';

    protected $description = 'Построить кэш таблицы сравнения панелей для админки (panel-settings)';

    public function handle(PanelRepository $panelRepository): int
    {
        $key = (string) config('panel.rotation_settings_cache_key', 'panel_rotation_settings_comparison_v2');
        $ttl = (int) config('panel.rotation_settings_cache_ttl', 900);

        try {
            $data = $panelRepository->compareAllStrategies();
            Cache::put($key, $data, $ttl);
            $panels = $data['panels'] ?? null;
            $count = $panels instanceof \Illuminate\Support\Collection
                ? $panels->count()
                : (is_array($panels) ? count($panels) : 0);
            $this->info("{$key}: ok, ttl={$ttl}s, panels={$count}");

            return 0;
        } catch (Throwable $e) {
            Log::error('panel:warm-rotation-settings failed', [
                'error' => $e->getMessage(),
                'source' => 'panel',
            ]);
            $this->error($e->getMessage());

            return 1;
        }
    }
}
