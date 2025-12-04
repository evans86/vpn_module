<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\VPN\ConnectionMonitorService;
use Illuminate\Support\Facades\Log;

class MonitorConnectionLimits extends Command
{
    protected $signature = 'vpn:monitor-fixed
                            {--threshold=3 : Maximum allowed unique IPs (violation at 4+ IPs)}
                            {--window=15 : Time window in minutes}';

    protected $description = 'Fixed VPN connection monitoring with proper parsing';

    private ConnectionMonitorService $monitorService;

    public function __construct(ConnectionMonitorService $monitorService)
    {
        parent::__construct();
        $this->monitorService = $monitorService;
    }

    public function handle()
    {
        $threshold = $this->option('threshold');
        $window = $this->option('window');

        $this->info("🚀 Starting FIXED VPN connection monitoring...");
        $this->info("🎯 Threshold: {$threshold} unique IPs");
        $this->info("⏰ Time window: {$window} minutes");
        $this->info("⏳ Please wait...");

        // Логируем начало проверки
        Log::info('🚀 Запуск проверки нарушений лимитов подключений', [
            'threshold' => $threshold,
            'window_minutes' => $window,
            'started_at' => now()->format('Y-m-d H:i:s')
        ]);

        $startTime = microtime(true);

        try {
            $results = $this->monitorService->monitorFixed($threshold, $window);

            $totalTime = round(microtime(true) - $startTime, 2);

            $this->info("\n✅ Monitoring completed in {$totalTime}s");
            $this->line("📋 Total violations found: {$results['violations_found']}");
            $this->line("🖥️  Total servers checked: {$results['total_servers']}");

            $this->info("\n📊 Servers checked:");
            foreach ($results['servers_checked'] as $server) {
                $line = "- {$server['host']}: " .
                    "{$server['users_count']} users, " .
                    "{$server['processing_time']}s";

                if (isset($server['data_notes'])) {
                    $line .= " ({$server['data_notes']})";
                }

                $this->line($line);
            }

            if (!empty($results['errors'])) {
                $this->error("\n❌ Errors:");
                foreach ($results['errors'] as $error) {
                    $this->error("- {$error}");
                }
            }

            // Логируем успешное завершение проверки
            Log::info('✅ Проверка нарушений лимитов подключений завершена', [
                'threshold' => $threshold,
                'window_minutes' => $window,
                'violations_found' => $results['violations_found'],
                'total_servers' => $results['total_servers'],
                'servers_checked' => count($results['servers_checked']),
                'errors_count' => count($results['errors'] ?? []),
                'execution_time_seconds' => $totalTime,
                'completed_at' => now()->format('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $totalTime = round(microtime(true) - $startTime, 2);

            Log::error('❌ Ошибка при проверке нарушений лимитов подключений', [
                'threshold' => $threshold,
                'window_minutes' => $window,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'execution_time_seconds' => $totalTime,
                'failed_at' => now()->format('Y-m-d H:i:s')
            ]);

            $this->error("❌ Ошибка: {$e->getMessage()}");
            throw $e;
        }
    }
}
