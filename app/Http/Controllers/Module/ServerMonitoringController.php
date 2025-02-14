<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Panel\Panel;
use App\Models\ServerMonitoring\ServerMonitoring;
use Carbon\Carbon;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class ServerMonitoringController extends Controller
{
    /**
     * @throws GuzzleException
     */
    public function index(Request $request)
    {
        // Получаем все сконфигурированные панели
        $panels = Panel::query()->where('panel_status', Panel::PANEL_CONFIGURED);

        // Если передан panel_id, фильтруем по конкретной панели
        if ($request->route('panel_id')) {
            $panels->where('id', $request->route('panel_id'));
        }

        $panels = $panels->get();

        // Собираем статистику для каждой панели
        $statistics = [];
        foreach ($panels as $panel) {
            // Получаем данные за последнюю неделю
            $oneWeekAgo = Carbon::now()->subWeek();
            $panelStats = ServerMonitoring::where('panel_id', $panel->id)
                ->where('created_at', '>=', $oneWeekAgo)
                ->orderBy('created_at', 'asc')
                ->get()
                ->groupBy(function ($item) {
                    return $item->created_at->format('Y-m-d H:00'); // Агрегация по часам
                })
                ->map(function ($group) {
                    return [
                        'created_at' => $group->first()->created_at->format('Y-m-d H:i:s'),
                        'statistics' => [
                            'cpu_usage' => $group->avg('statistics->cpu_usage'),
                            'mem_used_gb' => $group->avg('statistics->mem_used_gb'),
                            'online_users' => $group->avg('statistics->online_users'),
                        ],
                    ];
                });

            $statistics[$panel->id] = [
                'panel' => $panel,
                'data' => $panelStats,
            ];
        }

        Log::info('Statistics data:', ['statistics' => $statistics]);

        return view('module.server-monitoring.index', compact('statistics'));
    }
}
