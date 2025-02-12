<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Logging\DatabaseLogger;
use App\Models\Panel\Panel;
use App\Models\ServerMonitoring\ServerMonitoring;
use App\Services\Panel\PanelStrategy;
use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;

class ServerMonitoringController extends Controller
{
    private DatabaseLogger $logger;

    public function __construct(
        DatabaseLogger $logger
    )
    {
        $this->logger = $logger;
    }

    /**
     * @throws GuzzleException
     */
    public function index()
    {
        /**
         * @var Panel $panel
         */
        $panel = Panel::query()->where('panel_status', Panel::PANEL_CONFIGURED)->first();
        $strategy = new PanelStrategy($panel->panel);
        $strategy->getServerStats();

        // Получаем все сконфигурированные панели
        $panels = Panel::query()->where('panel_status', Panel::PANEL_CONFIGURED)->get();

        // Собираем статистику для каждой панели
        $statistics = [];
        foreach ($panels as $panel) {
            // Получаем данные за последнюю неделю
            $oneWeekAgo = Carbon::now()->subWeek();
            $panelStats = ServerMonitoring::where('panel_id', $panel->id)
                ->where('created_at', '>=', $oneWeekAgo)
                ->orderBy('created_at', 'asc')
                ->get();

            // Формируем данные для графика
            $statistics[$panel->id] = [
                'panel' => $panel,
                'data' => $panelStats->map(function ($stat) {
                    return [
                        'created_at' => $stat->created_at->format('Y-m-d H:i:s'),
                        'statistics' => json_decode($stat->statistics, true),
                    ];
                }),
            ];
        }

        return view('module.server-monitoring.index');
    }
}
