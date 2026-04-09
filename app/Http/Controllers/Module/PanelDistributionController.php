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
 * Единая страница: снимок по панелям, scope v2, ротация/ошибки (раньше было на «Настройки распределения»).
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
                'error' => 'Данные для таблицы ещё не собраны или кэш истёк. На сервере выполните один раз: php artisan panel:warm-rotation-settings — затем обновите страницу. Для автообновления включите cron (команда в расписании Laravel, см. PANEL_ROTATION_SETTINGS_WARM_* в .env).',
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

        $snapshotPanels = [];
        foreach ($monthlyStats as $row) {
            $p = $panelModels->get($row['panel_id']);
            $traffic = $row['current_month']['traffic'] ?? null;
            $snapshotPanels[] = [
                'panel_id' => $row['panel_id'],
                'server_name' => $row['server_name'],
                'provider' => $p && $p->server ? ($p->server->provider ?? '—') : '—',
                'tariff_label' => $p && $p->server ? TariffTier::label($p->server->tariff_tier ?? '') : '—',
                'period_label' => ($row['period']['current']['name'] ?? 'Месяц') . ' ' . ($row['period']['current']['year'] ?? ''),
                'used_tb' => is_array($traffic) ? ($traffic['used_tb'] ?? null) : null,
                'limit_tb' => is_array($traffic) ? ($traffic['limit_tb'] ?? null) : null,
                'used_percent' => is_array($traffic) ? ($traffic['used_percent'] ?? null) : null,
            ];
        }

        return view('module.panel-distribution.index', [
            'panelsByTier' => $panelsByTier,
            'v2Enabled' => (bool) config('panel.selection_v2_enabled', false),
            'v2CacheTtl' => (int) config('panel.selection_v2_cache_ttl', 0),
            'tariffTier' => (string) config('panel.activation_tariff_tier', 'full'),
            'comparison' => $comparison,
            'panelsWithErrors' => $panelsWithErrors,
            'errorHistory' => $errorHistory,
            'excludedPanels' => $excludedPanels,
            'snapshotPanels' => $snapshotPanels,
        ]);
    }
}
