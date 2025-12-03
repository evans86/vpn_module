<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\VPN\ConnectionMonitorService;

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

        $startTime = microtime(true);

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
    }
}
