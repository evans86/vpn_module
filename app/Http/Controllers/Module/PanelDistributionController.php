<?php

namespace App\Http\Controllers\Module;

use App\Constants\TariffTier;
use App\Http\Controllers\Controller;
use App\Models\Panel\Panel;
use App\Models\Panel\PanelErrorHistory;
use App\Repositories\Panel\PanelRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Единая страница: сводная таблица (scope v2, месячный учёт, Marzban), ошибки и исключения.
 */
class PanelDistributionController extends Controller
{
    public function index(PanelRepository $panelRepository): View
    {
        /** @var array<string, Collection<int, Panel>> $panelsByTier */
        $panelsByTier = [];
        foreach (TariffTier::all() as $tier) {
            $panelsByTier[$tier] = Panel::query()
                ->where('panel', Panel::MARZBAN)
                ->where('panel_status', Panel::PANEL_CONFIGURED)
                ->where('has_error', false)
                ->where('excluded_from_rotation', false)
                ->whereHas('server', function ($q) use ($tier) {
                    $q->where('tariff_tier', $tier);
                })
                ->with(['server.location'])
                ->orderByDesc('selection_scope_score')
                ->orderBy('id')
                ->limit(500)
                ->get();
        }

        $cacheKey = (string) config('panel.rotation_settings_cache_key', 'panel_rotation_settings_comparison_v2');
        $comparison = Cache::get($cacheKey);
        if ($comparison === null) {
            $comparison = [
                'error' => 'Данные Marzban ещё не собраны или кэш истёк. Выполните: php artisan panel:warm-rotation-settings и обновите страницу (cron, PANEL_ROTATION_SETTINGS_WARM_* в .env).',
            ];
        }

        $panelsWithErrors = $panelRepository->getPanelsWithErrors();

        $errorHistory = [];
        if ($panelsWithErrors->isNotEmpty()) {
            $panelIds = $panelsWithErrors->pluck('id')->toArray();
            $histories = PanelErrorHistory::whereIn('panel_id', $panelIds)
                ->orderBy('error_occurred_at', 'desc')
                ->get();
            foreach ($histories->groupBy('panel_id') as $panelId => $items) {
                $errorHistory[$panelId] = $items->sortByDesc('error_occurred_at')->take(10)->values();
            }
            foreach ($panelIds as $id) {
                if (! isset($errorHistory[$id])) {
                    $errorHistory[$id] = collect();
                }
            }
        }

        $excludedPanels = Panel::where('excluded_from_rotation', true)
            ->where('panel_status', Panel::PANEL_CONFIGURED)
            ->where('has_error', false)
            ->with('server')
            ->get();

        $monthlyStats = $panelRepository->getMonthlyStatistics();
        $panelModels = Panel::query()
            ->whereIn('id', array_column($monthlyStats, 'panel_id'))
            ->with('server')
            ->get()
            ->keyBy('id');

        $snapshotByPanelId = [];
        foreach ($monthlyStats as $row) {
            $pid = (int) $row['panel_id'];
            $p = $panelModels->get($pid);
            $traffic = $row['current_month']['traffic'] ?? null;
            $snapshotByPanelId[$pid] = [
                'panel_id' => $pid,
                'server_name' => $row['server_name'],
                'provider' => $p && $p->server ? ($p->server->provider ?? '—') : '—',
                'tariff_label' => $p && $p->server ? TariffTier::label($p->server->tariff_tier ?? '') : '—',
                'period_label' => ($row['period']['current']['name'] ?? 'Месяц').' '.($row['period']['current']['year'] ?? ''),
                'used_tb' => is_array($traffic) ? ($traffic['used_tb'] ?? null) : null,
                'limit_tb' => is_array($traffic) ? ($traffic['limit_tb'] ?? null) : null,
                'used_percent' => is_array($traffic) ? ($traffic['used_percent'] ?? null) : null,
            ];
        }

        $marzbanByPanelId = $this->marzbanByPanelId($comparison);

        /** @var list<array{tier: string, label: string, rows: list<array{panel: Panel, snapshot: ?array, marzban: ?array}>}> $distributionTiers */
        $distributionTiers = [];
        foreach (TariffTier::all() as $tier) {
            $rows = [];
            foreach ($panelsByTier[$tier] as $panel) {
                $id = (int) $panel->id;
                $rows[] = [
                    'panel' => $panel,
                    'snapshot' => $snapshotByPanelId[$id] ?? null,
                    'marzban' => $marzbanByPanelId[$id] ?? null,
                ];
            }
            $distributionTiers[] = [
                'tier' => $tier,
                'label' => TariffTier::label($tier),
                'rows' => $rows,
            ];
        }

        return view('module.panel-distribution.index', [
            'distributionTiers' => $distributionTiers,
            'comparison' => $comparison,
            'panelsWithErrors' => $panelsWithErrors,
            'errorHistory' => $errorHistory,
            'excludedPanels' => $excludedPanels,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $comparison
     * @return array<int, array<string, mixed>>
     */
    private function marzbanByPanelId(?array $comparison): array
    {
        if ($comparison === null || isset($comparison['error']) || empty($comparison['panels']) || ! is_array($comparison['panels'])) {
            return [];
        }
        $out = [];
        foreach ($comparison['panels'] as $p) {
            if (! is_array($p) || ! isset($p['id'])) {
                continue;
            }
            $out[(int) $p['id']] = $p;
        }

        return $out;
    }
}
