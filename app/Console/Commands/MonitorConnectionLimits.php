<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\VPN\ConnectionMonitorService;

class MonitorConnectionLimits extends Command
{
    protected $signature = 'vpn:monitor-connections
                            {--threshold=2 : Maximum allowed unique IPs per subscription}
                            {--window=10 : Time window in minutes for analysis}';

    protected $description = 'Monitor VPN connections for subscription sharing violations using sliding window';

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

        $this->info("Starting VPN connection monitoring (Sliding Window)...");
        $this->info("Monitoring subscription sharing (unique IPs per UUID)");
        $this->info("Threshold: {$threshold} unique IPs");
        $this->info("Time Window: {$window} minutes");

        $results = $this->monitorService->monitorSlidingWindow($threshold, $window);

        // Вывод результатов
        $this->displayResults($results);

        // Статистика
        $stats = $this->monitorService->getMonitoringStats();
        $this->info("\nMonitoring Statistics:");
        $this->line("Total violations: {$stats['total_violations']}");
        $this->line("Active violations: {$stats['active_violations']}");
        $this->line("Today violations: {$stats['today_violations']}");
        $this->line("Active servers: {$stats['servers_count']}");
    }

    private function displayResults(array $results): void
    {
        $this->info("\nSliding Window Monitoring Results:");
        $this->line("Total violations found: {$results['violations_found']}");
        $this->line("Total servers checked: {$results['total_servers']}");

        if (!empty($results['errors'])) {
            $this->error("\nErrors:");
            foreach ($results['errors'] as $error) {
                $this->error("- {$error}");
            }
        }

        $this->info("\nServers checked:");
        foreach ($results['servers_checked'] as $server) {
            $this->line("- {$server['host']}: {$server['violations']} violations, {$server['users_checked']} users checked ({$server['time_window']})");
        }
    }
}
