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
        $panels = Panel::query()->where('panel_status', Panel::PANEL_CONFIGURED);

        if ($request->route('panel_id')) {
            $panels->where('id', $request->route('panel_id'));
        }

        $panels = $panels->get();

        $statistics = [];
        foreach ($panels as $panel) {
            $oneWeekAgo = Carbon::now()->subWeek();

            // Используем кэширование
            $statistics[$panel->id] = Cache::remember('panel_stats_' . $panel->id, 3600, function () use ($panel, $oneWeekAgo) {
                $data = [];

                ServerMonitoring::where('panel_id', $panel->id)
                    ->where('created_at', '>=', $oneWeekAgo)
                    ->orderBy('created_at', 'asc')
                    ->select(['id', 'panel_id', 'statistics', 'created_at'])
                    ->chunk(100, function ($stats) use (&$data) {
                        foreach ($stats as $stat) {
                            $stats = json_decode($stat->statistics, true);
                            $stats['mem_used_gb'] = $stats['mem_used'] / (1024 * 1024 * 1024);
                            $stats['mem_total_gb'] = $stats['mem_total'] / (1024 * 1024 * 1024);

                            $data[] = [
                                'created_at' => $stat->created_at->format('Y-m-d H:i:s'),
                                'statistics' => $stats,
                            ];
                        }
                    });

                return [
                    'panel' => $panel,
                    'data' => $data,
                ];
            });
        }

        return view('module.server-monitoring.index', compact('statistics'));
    }
}
