<?php

namespace App\Console\Commands;

use App\Models\Panel\Panel;
use App\Repositories\Panel\PanelRepository;
use Illuminate\Console\Command;
use Throwable;

/**
 * Прогрев кэша интеллектуального выбора панелей (Cache::remember в PanelRepository),
 * чтобы активация и покупка чаще попадали в уже посчитанный выбор.
 */
class WarmPanelSelectionCacheCommand extends Command
{
    protected $signature = 'panel:warm-selection-cache';

    protected $description = 'Прогрев кэша выбора панелей Marzban (общий + по провайдерам из multi_provider_slots)';

    public function handle(PanelRepository $panelRepository): int
    {
        $providers = [];
        if (filter_var(config('panel.multi_provider_allow_all', false), FILTER_VALIDATE_BOOLEAN)) {
            $providers = $panelRepository->getDistinctRotationProviderCodes(null);
        } else {
            $slots = config('panel.multi_provider_slots', []);
            if (is_array($slots)) {
                foreach ($slots as $p) {
                    $p = trim((string) $p);
                    if ($p !== '') {
                        $providers[] = $p;
                    }
                }
            }
        }

        $ok = 0;
        $fail = 0;

        try {
            $panel = $panelRepository->getOptimizedMarzbanPanel(null, false);
            if ($this->mark($panel, 'default')) {
                $ok++;
            } else {
                $fail++;
            }
        } catch (Throwable $e) {
            $fail++;
            $this->error('default: ' . $e->getMessage());
        }

        if ($providers !== []) {
            try {
                $byProvider = $panelRepository->getOptimizedMarzbanPanelsForProviders($providers, null, false);
                foreach ($byProvider as $provider => $panel) {
                    if ($this->mark($panel, $provider)) {
                        $ok++;
                    } else {
                        $fail++;
                    }
                }
            } catch (Throwable $e) {
                $fail++;
                $this->error('multi-provider warm: ' . $e->getMessage());
            }
        }

        $this->info("Panel selection cache warm: ok={$ok}, skip/fail={$fail}");

        return $fail > 0 && $ok === 0 ? 1 : 0;
    }

    private function mark(?Panel $panel, string $label): bool
    {
        if ($panel) {
            if ($this->output->isVerbose()) {
                $this->line("[{$label}] panel_id={$panel->id}");
            }

            return true;
        }

        $this->warn("[{$label}] нет подходящей панели (кэш не заполнен)");

        return false;
    }
}
