<?php

namespace App\Services\Panel;

use App\Models\Panel\Panel;
use App\Models\ServerMonitoring\ServerMonitoring;
use App\Repositories\Panel\PanelRepository;
use App\Support\SelectionScopeCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Расчёт и сохранение selection scope (жёсткая формула по трафику провайдера + CPU Marzban).
 */
class PanelSelectionScopeService
{
    public function __construct(
        private PanelRepository $panelRepository
    ) {
    }

    /**
     * Пересчёт для одной панели: пишет selection_scope_score, selection_scope_computed_at, selection_scope_meta.
     */
    public function computeAndPersist(Panel $panel): void
    {
        $panel->loadMissing('server');

        $now = Carbon::now();
        $currentDay = max(1, (int) $now->format('j'));
        $daysInMonth = (int) $now->daysInMonth;

        $cpuPercent = $this->latestCpuUsagePercent((int) $panel->id);

        $traffic = $this->panelRepository->getServerTrafficData($panel);
        $limitTb = 0.0;
        $usedTb = 0.0;
        if ($traffic && ! empty($traffic['limit'])) {
            $limitBytes = (float) $traffic['limit'];
            $usedBytes = (float) ($traffic['current_month'] ?? 0);
            $limitTb = $limitBytes > 0 ? $limitBytes / (1024 ** 4) : 0.0;
            $usedTb = $usedBytes / (1024 ** 4);
        }

        $forecastTb = ($limitTb > 0 && $usedTb >= 0)
            ? $usedTb * ($daysInMonth / $currentDay)
            : 0.0;

        $sTraffic = ($limitTb > 0)
            ? max(0.0, 1.0 - ($forecastTb / $limitTb))
            : 0.0;
        $sCpu = max(0.0, 1.0 - min(1.0, $cpuPercent / 100.0));

        $scope = SelectionScopeCalculator::hardByCpuPercent(
            $limitTb,
            $usedTb,
            $currentDay,
            $daysInMonth,
            $cpuPercent
        );

        $meta = [
            'forecast_tb' => round($forecastTb, 4),
            'limit_tb' => round($limitTb, 4),
            'used_tb' => round($usedTb, 4),
            's_traffic' => round($sTraffic, 4),
            's_cpu' => round($sCpu, 4),
            'cpu_percent' => round($cpuPercent, 2),
            'formula' => 'hard_product',
            'day' => $currentDay,
            'days_in_month' => $daysInMonth,
            'traffic_source' => $traffic ? 'provider_api' : 'none',
        ];

        $panel->selection_scope_score = $scope;
        $panel->selection_scope_computed_at = $now;
        $panel->selection_scope_meta = $meta;
        $panel->save();

        Log::debug('PANEL_SCOPE_RECALC', [
            'panel_id' => $panel->id,
            'scope' => $scope,
            'source' => 'panel',
        ]);
    }

    private function latestCpuUsagePercent(int $panelId): float
    {
        $row = ServerMonitoring::query()
            ->where('panel_id', $panelId)
            ->orderByDesc('created_at')
            ->first();

        if (! $row) {
            return 0.0;
        }

        $decoded = is_string($row->statistics) ? json_decode($row->statistics, true) : $row->statistics;
        if (! is_array($decoded)) {
            return 0.0;
        }

        return (float) ($decoded['cpu_usage'] ?? 0);
    }
}
