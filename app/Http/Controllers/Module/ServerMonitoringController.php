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
        // Увеличиваем лимит памяти (временное решение)
        ini_set('memory_limit', '256M');

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

            // Инициализируем массив для данных панели
            $statistics[$panel->id] = [
                'panel' => $panel,
                'data' => [],
            ];

            // Используем chunk для обработки данных по частям
            ServerMonitoring::where('panel_id', $panel->id)
                ->where('created_at', '>=', $oneWeekAgo)
                ->orderBy('created_at', 'asc')
                ->select(['id', 'panel_id', 'statistics', 'created_at']) // Выбираем только нужные поля
                ->chunk(100, function ($stats) use (&$statistics, $panel) {
                    foreach ($stats as $stat) {
                        $stats = json_decode($stat->statistics, true);

                        // Конвертируем память из байтов в гигабайты
                        $stats['mem_used_gb'] = $stats['mem_used'] / (1024 * 1024 * 1024);
                        $stats['mem_total_gb'] = $stats['mem_total'] / (1024 * 1024 * 1024);

                        // Добавляем данные в массив
                        $statistics[$panel->id]['data'][] = [
                            'created_at' => $stat->created_at->format('Y-m-d H:i:s'),
                            'statistics' => $stats,
                        ];
                    }
                });
        }

        // Логируем объем данных (для отладки)
        Log::info('Statistics data:', ['statistics_count' => count($statistics)]);

        return view('module.server-monitoring.index', compact('statistics'));
    }
}
