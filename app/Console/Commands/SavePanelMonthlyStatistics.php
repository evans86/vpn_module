<?php

namespace App\Console\Commands;

use App\Models\Panel\Panel;
use App\Models\Panel\PanelMonthlyStatistics;
use App\Repositories\Panel\PanelRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SavePanelMonthlyStatistics extends Command
{
    protected $signature = 'panels:save-monthly-statistics';
    protected $description = 'Save monthly statistics for all panels (run on last day of month)';

    protected PanelRepository $panelRepository;

    public function __construct(PanelRepository $panelRepository)
    {
        parent::__construct();
        $this->panelRepository = $panelRepository;
    }

    public function handle(): int
    {
        $now = Carbon::now();
        
        // Проверяем, является ли сегодня последний день месяца
        // Если нет - выходим без ошибки (команда может быть запущена в cron каждый день)
        if (!$now->isLastOfMonth()) {
            $this->info("Today is not the last day of month. Skipping...");
            return 0;
        }
        
        $lastMonth = $now->copy()->subMonth();
        
        $year = $lastMonth->year;
        $month = $lastMonth->month;
        
        $this->info("Saving monthly statistics for {$year}-{$month}");
        
        $panels = $this->panelRepository->getAllConfiguredPanels();
        $saved = 0;
        $errors = 0;
        
        foreach ($panels as $panel) {
            try {
                // Получаем статистику за прошлый месяц
                $lastMonthStart = $lastMonth->copy()->startOfMonth();
                $lastMonthEnd = $lastMonth->copy()->endOfMonth();
                
                $stats = $this->panelRepository->getPanelStatsForPeriod($panel, $lastMonthStart, $lastMonthEnd);
                
                // Получаем данные о трафике
                $trafficData = $this->panelRepository->getServerTrafficData($panel);
                
                // Используем last_month из API, так как сохраняем данные за прошлый месяц
                $trafficUsedBytes = null;
                $trafficLimitBytes = null;
                $trafficUsedPercent = null;
                
                if ($trafficData) {
                    $trafficLimitBytes = $trafficData['limit'];
                    
                    // Используем last_month, так как сохраняем за прошлый месяц
                    if (isset($trafficData['last_month'])) {
                        $trafficUsedBytes = $trafficData['last_month'];
                        $trafficUsedPercent = $trafficLimitBytes > 0 
                            ? round(($trafficUsedBytes / $trafficLimitBytes) * 100, 2) 
                            : 0;
                    }
                }
                
                // Сохраняем или обновляем статистику
                PanelMonthlyStatistics::updateOrCreate(
                    [
                        'panel_id' => $panel->id,
                        'year' => $year,
                        'month' => $month,
                    ],
                    [
                        'active_users' => $stats['active_users'],
                        'online_users' => $stats['online_users'],
                        'traffic_used_bytes' => $trafficUsedBytes,
                        'traffic_limit_bytes' => $trafficLimitBytes,
                        'traffic_used_percent' => $trafficUsedPercent,
                    ]
                );
                
                $saved++;
                $this->line("Saved statistics for panel ID-{$panel->id}");
                
            } catch (\Exception $e) {
                $errors++;
                Log::error('Failed to save monthly statistics for panel', [
                    'panel_id' => $panel->id,
                    'year' => $year,
                    'month' => $month,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed to save statistics for panel ID-{$panel->id}: {$e->getMessage()}");
            }
        }
        
        $this->info("Completed: {$saved} saved, {$errors} errors");
        
        return 0;
    }
}
