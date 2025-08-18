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
            $oneWeekAgo = Carbon::now()->subDays(3);
            $panelStats = ServerMonitoring::where('panel_id', $panel->id)
                ->where('created_at', '>=', $oneWeekAgo)
                ->orderBy('created_at', 'asc')
                ->get();

            // Формируем данные для графика
            $statistics[$panel->id] = [
                'panel' => $panel,
                'data' => $panelStats->map(function ($stat) {
                    $stats = json_decode($stat->statistics, true);

                    // Конвертируем память из байтов в гигабайты
                    $stats['mem_used_gb'] = $stats['mem_used'] / (1024 * 1024 * 1024); // 1 ГБ = 1024 МБ = 1024 * 1024 КБ = 1024 * 1024 * 1024 Б
                    $stats['mem_total_gb'] = $stats['mem_total'] / (1024 * 1024 * 1024);

                    return [
                        'created_at' => $stat->created_at->format('Y-m-d H:i:s'),
                        'statistics' => $stats,
                    ];
                }),
            ];
        }

        Log::info('Statistics data:', ['statistics' => $statistics]);

        return view('module.server-monitoring.index', compact('statistics'));
    }
}
