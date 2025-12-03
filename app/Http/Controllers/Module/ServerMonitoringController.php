<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Panel\Panel;
use App\Models\ServerMonitoring\ServerMonitoring;
use Carbon\Carbon;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ServerMonitoringController extends Controller
{
    /**
     * @throws GuzzleException
     */
    public function index(Request $request)
    {
        // Получаем параметры фильтрации
        $days = (int) $request->get('days', 1); // По умолчанию 1 день
        $days = min(max($days, 1), 7); // Ограничиваем от 1 до 7 дней
        $panelId = $request->get('panel_id') ?: $request->route('panel_id');
        $limit = (int) $request->get('limit', 500); // Лимит записей (по умолчанию 500)
        $limit = min(max($limit, 100), 1000); // Ограничиваем от 100 до 1000

        // Получаем все сконфигурированные панели
        $panels = Panel::query()->where('panel_status', Panel::PANEL_CONFIGURED);

        // Если передан panel_id, фильтруем по конкретной панели
        if ($panelId) {
            $panels->where('id', $panelId);
        }

        $panels = $panels->get();

        // Собираем статистику для каждой панели с кэшированием
        $statistics = [];
        foreach ($panels as $panel) {
            $cacheKey = "server_monitoring_{$panel->id}_{$days}_{$limit}";
            
            // Кэшируем на 2 минуты
            $panelData = Cache::remember($cacheKey, 120, function () use ($panel, $days, $limit) {
                $dateFrom = Carbon::now()->subDays($days);
                
                // Оптимизированный запрос: выбираем только нужные поля и ограничиваем количество
                // Используем сырой SQL для оптимизации или chunk для больших данных
                $totalCount = ServerMonitoring::where('panel_id', $panel->id)
                    ->where('created_at', '>=', $dateFrom)
                    ->count();

                // Если записей много, используем выборку с интервалами (каждую N-ю запись)
                if ($totalCount > $limit) {
                    $step = ceil($totalCount / $limit);
                    $panelStats = ServerMonitoring::where('panel_id', $panel->id)
                        ->where('created_at', '>=', $dateFrom)
                        ->orderBy('created_at', 'asc')
                        ->get()
                        ->filter(function ($item, $index) use ($step) {
                            return $index % $step === 0;
                        })
                        ->take($limit);
                } else {
                    // Если записей немного, берем все
                    $panelStats = ServerMonitoring::where('panel_id', $panel->id)
                        ->where('created_at', '>=', $dateFrom)
                        ->orderBy('created_at', 'asc')
                        ->limit($limit)
                        ->get(['id', 'panel_id', 'statistics', 'created_at']);
                }

                // Формируем данные для графика с оптимизацией памяти
                $data = [];
                foreach ($panelStats as $stat) {
                    $stats = json_decode($stat->statistics, true);
                    
                    if (!$stats) {
                        continue; // Пропускаем некорректные данные
                    }

                    // Конвертируем память из байтов в гигабайты
                    $memUsed = $stats['mem_used'] ?? 0;
                    $memTotal = $stats['mem_total'] ?? 1;
                    
                    $data[] = [
                        'created_at' => $stat->created_at->format('Y-m-d H:i:s'),
                        'statistics' => [
                            'cpu_usage' => $stats['cpu_usage'] ?? 0,
                            'mem_used' => $memUsed,
                            'mem_total' => $memTotal,
                            'mem_used_gb' => $memUsed / (1024 * 1024 * 1024),
                            'mem_total_gb' => $memTotal / (1024 * 1024 * 1024),
                            'online_users' => $stats['online_users'] ?? 0,
                            'users_active' => $stats['users_active'] ?? 0,
                            'total_user' => $stats['total_user'] ?? 0,
                            'users_expired' => $stats['users_expired'] ?? 0,
                            'users_limited' => $stats['users_limited'] ?? 0,
                            'users_on_hold' => $stats['users_on_hold'] ?? 0,
                            'users_disabled' => $stats['users_disabled'] ?? 0,
                            'incoming_bandwidth' => $stats['incoming_bandwidth'] ?? 0,
                            'outgoing_bandwidth' => $stats['outgoing_bandwidth'] ?? 0,
                        ],
                    ];
                }

                return $data;
            });

            $statistics[$panel->id] = [
                'panel' => $panel,
                'data' => collect($panelData),
                'total_records' => ServerMonitoring::where('panel_id', $panel->id)
                    ->where('created_at', '>=', Carbon::now()->subDays($days))
                    ->count(),
            ];
        }

        // Получаем список всех панелей для фильтра
        $allPanels = Panel::where('panel_status', Panel::PANEL_CONFIGURED)
            ->orderBy('id')
            ->get(['id', 'panel_adress']);

        return view('module.server-monitoring.index', compact('statistics', 'allPanels', 'days', 'panelId', 'limit'));
    }
}
