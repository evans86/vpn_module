<?php

namespace App\Console\Commands;

use App\Models\Panel\Panel;
use App\Services\Panel\PanelSelectionScopeService;
use Illuminate\Console\Command;

class RecalculatePanelSelectionScopeCommand extends Command
{
    protected $signature = 'panel:recalculate-selection-scope {--panel= : ID панели только для одной}';

    protected $description = 'Пересчёт selection_scope_score для панелей (трафик провайдера + CPU из server_monitoring)';

    public function handle(PanelSelectionScopeService $scopeService): int
    {
        if (! config('panel.scope_recalc_enabled', true)) {
            $this->warn('panel.scope_recalc_enabled = false, выход.');

            return self::SUCCESS;
        }

        $singleId = $this->option('panel');
        $query = Panel::query()
            ->where('panel', Panel::MARZBAN)
            ->where('panel_status', Panel::PANEL_CONFIGURED)
            ->where('has_error', false)
            ->where('excluded_from_rotation', false)
            ->with('server');

        if ($singleId !== null && $singleId !== '') {
            $query->where('id', (int) $singleId);
        }

        $panels = $query->get();
        $n = 0;
        foreach ($panels as $panel) {
            try {
                $scopeService->computeAndPersist($panel);
                $n++;
            } catch (\Throwable $e) {
                $this->error("Panel {$panel->id}: {$e->getMessage()}");
            }
        }

        $this->info("Готово, обработано панелей: {$n}");

        return self::SUCCESS;
    }
}
