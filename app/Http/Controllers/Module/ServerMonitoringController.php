<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Logging\DatabaseLogger;
use App\Models\Panel\Panel;
use App\Models\ServerMonitoring\ServerMonitoring;
use App\Services\Panel\PanelStrategy;
use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

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
//        /**
//         * @var Panel $panel
//         */
//        $panel = Panel::query()->where('panel_status', Panel::PANEL_CONFIGURED)->first();
//        $strategy = new PanelStrategy($panel->panel);
//        $strategy->getServerStats();

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

        // Логируем данные для отладки
        Log::info('Statistics data:', ['statistics' => $statistics]);

        // Передаем данные в представление
        return view('module.server-monitoring.index', compact('statistics'));
    }
}
