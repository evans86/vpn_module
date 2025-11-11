<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\VPN\ConnectionMonitorService;

class MonitorConnectionLimits extends Command
{
    protected $signature = 'vpn:monitor-connections
                            {--threshold=2 : Maximum allowed unique IPs per subscription}
                            {--window=10 : Time window in minutes for analysis}';

    protected $description = 'Monitor VPN connections for subscription sharing violations';

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

        $this->info("🚀 Starting VPN connection monitoring...");
        $this->info("🎯 Threshold: {$threshold} unique IPs");
        $this->info("⏰ Time Window: {$window} minutes");
        $this->info("⏳ Please wait...");

        $startTime = microtime(true);

        $results = $this->monitorService->monitorSlidingWindow($threshold, $window);

        $totalTime = round(microtime(true) - $startTime, 2);

        // Вывод результатов
        $this->displayResults($results, $totalTime);

        // Статистика
        $stats = $this->monitorService->getMonitoringStats();
        $this->info("\n📈 Monitoring Statistics:");
        $this->line("Total violations: {$stats['total_violations']}");
        $this->line("Active violations: {$stats['active_violations']}");
        $this->line("Today violations: {$stats['today_violations']}");
        $this->line("Active servers: {$stats['servers_count']}");
    }

    private function displayResults(array $results, float $totalTime): void
    {
        $this->info("\n✅ Monitoring completed in {$totalTime}s");
        $this->line("📋 Total violations found: {$results['violations_found']}");
        $this->line("🖥️  Total servers checked: {$results['total_servers']}");

        if (!empty($results['errors'])) {
            $this->error("\n❌ Errors:");
            foreach ($results['errors'] as $error) {
                $this->error("- {$error}");
            }
        }

        $this->info("\n📊 Servers checked:");
        foreach ($results['servers_checked'] as $server) {
            $line = "- {$server['host']}: {$server['violations']} violations, " .
                "{$server['users_checked']} users, " .
                "{$server['processing_time']}s";

            // Добавляем unique_ips_total если есть
            if (isset($server['unique_ips_total'])) {
                $line .= ", {$server['unique_ips_total']} total IPs";
            }

            // Добавляем lines_processed если есть
            if (isset($server['lines_processed'])) {
                $line .= ", {$server['lines_processed']} lines";
            }

            $this->line($line);
        }
    }
}
