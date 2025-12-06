<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Repositories\Panel\PanelRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf;

class PanelStatisticsController extends Controller
{
    protected PanelRepository $panelRepository;

    public function __construct(PanelRepository $panelRepository)
    {
        $this->panelRepository = $panelRepository;
    }

    /**
     * Отображение статистики по панелям
     */
    public function index()
    {
        $statistics = $this->panelRepository->getMonthlyStatistics();
        
        // Сортируем по ID панели
        usort($statistics, function($a, $b) {
            return $a['panel_id'] <=> $b['panel_id'];
        });
        
        // Вычисляем общие итоги
        $summary = $this->calculateSummary($statistics);
        
        return view('module.panel-statistics.index', [
            'statistics' => $statistics,
            'summary' => $summary,
        ]);
    }

    /**
     * Экспорт статистики в PDF
     */
    public function exportPdf()
    {
        $statistics = $this->panelRepository->getMonthlyStatistics();
        
        // Сортируем по ID панели
        usort($statistics, function($a, $b) {
            return $a['panel_id'] <=> $b['panel_id'];
        });
        
        // Вычисляем общие итоги
        $summary = $this->calculateSummary($statistics);
        
        $now = Carbon::now();
        $lastMonth = $now->copy()->subMonth();
        
        $pdf = Pdf::loadView('module.panel-statistics.pdf', [
            'statistics' => $statistics,
            'summary' => $summary,
            'currentMonth' => $now->locale('ru')->monthName . ' ' . $now->year,
            'lastMonth' => $lastMonth->locale('ru')->monthName . ' ' . $lastMonth->year,
            'generatedAt' => Carbon::now()->format('d.m.Y H:i'),
        ])->setPaper('a4', 'landscape');
        
        $filename = 'panel_statistics_' . $now->format('Y_m') . '.pdf';
        
        return $pdf->download($filename);
    }

    /**
     * Вычисление общих итогов
     */
    private function calculateSummary(array $statistics): array
    {
        $summary = [
            'total_panels' => count($statistics),
            'current_month' => [
                'total_active_users' => 0,
                'total_online_users' => 0,
                'total_traffic_used_tb' => 0,
                'total_traffic_limit_tb' => 0,
                'avg_traffic_percent' => 0,
            ],
            'last_month' => [
                'total_active_users' => 0,
                'total_online_users' => 0,
                'total_traffic_used_tb' => 0,
                'total_traffic_limit_tb' => 0,
                'avg_traffic_percent' => 0,
            ],
            'changes' => [
                'active_users' => 0,
                'online_users' => 0,
                'traffic_percent' => 0,
            ],
        ];
        
        $panelsWithTraffic = 0;
        $trafficPercentSum = 0;
        
        foreach ($statistics as $stat) {
            // Текущий месяц
            if ($stat['current_month']['active_users'] !== null) {
                $summary['current_month']['total_active_users'] += $stat['current_month']['active_users'];
            }
            if ($stat['current_month']['online_users'] !== null) {
                $summary['current_month']['total_online_users'] += $stat['current_month']['online_users'];
            }
            if ($stat['current_month']['traffic']) {
                $summary['current_month']['total_traffic_used_tb'] += $stat['current_month']['traffic']['used_tb'];
                $summary['current_month']['total_traffic_limit_tb'] += $stat['current_month']['traffic']['limit_tb'];
                $trafficPercentSum += $stat['current_month']['traffic']['used_percent'];
                $panelsWithTraffic++;
            }
            
            // Прошлый месяц
            if ($stat['last_month']['active_users'] !== null) {
                $summary['last_month']['total_active_users'] += $stat['last_month']['active_users'];
            }
            if ($stat['last_month']['online_users'] !== null) {
                $summary['last_month']['total_online_users'] += $stat['last_month']['online_users'];
            }
            if ($stat['last_month']['traffic']) {
                $summary['last_month']['total_traffic_used_tb'] += $stat['last_month']['traffic']['used_tb'];
                $summary['last_month']['total_traffic_limit_tb'] += $stat['last_month']['traffic']['limit_tb'];
            }
            
            // Изменения
            if ($stat['changes']['active_users'] !== null) {
                $summary['changes']['active_users'] += $stat['changes']['active_users'];
            }
            if ($stat['changes']['online_users'] !== null) {
                $summary['changes']['online_users'] += $stat['changes']['online_users'];
            }
        }
        
        if ($panelsWithTraffic > 0) {
            $summary['current_month']['avg_traffic_percent'] = round($trafficPercentSum / $panelsWithTraffic, 2);
        }
        
        // Вычисляем средний процент трафика за прошлый месяц
        $lastMonthTrafficPercentSum = 0;
        $lastMonthPanelsWithTraffic = 0;
        foreach ($statistics as $stat) {
            if ($stat['last_month']['traffic']) {
                $lastMonthTrafficPercentSum += $stat['last_month']['traffic']['used_percent'];
                $lastMonthPanelsWithTraffic++;
            }
        }
        if ($lastMonthPanelsWithTraffic > 0) {
            $summary['last_month']['avg_traffic_percent'] = round($lastMonthTrafficPercentSum / $lastMonthPanelsWithTraffic, 2);
        }
        
        // Вычисляем изменение среднего процента трафика
        if ($summary['last_month']['avg_traffic_percent'] > 0) {
            $summary['changes']['traffic_percent'] = round(
                $summary['current_month']['avg_traffic_percent'] - $summary['last_month']['avg_traffic_percent'],
                2
            );
        }
        
        return $summary;
    }
}

