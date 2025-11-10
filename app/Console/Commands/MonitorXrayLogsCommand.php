<?php

namespace App\Console\Commands;

use App\Models\Panel\Panel;
use App\Services\VPN\XrayLogMonitorService;
use Illuminate\Console\Command;

class MonitorXrayLogsCommand extends Command
{
    protected $signature = 'vpn:monitor-xray-logs';
    protected $description = 'Monitor Xray logs for connection limit violations';

    private XrayLogMonitorService $logMonitorService;

    public function __construct(XrayLogMonitorService $logMonitorService)
    {
        parent::__construct();
        $this->logMonitorService = $logMonitorService;
    }

    public function handle()
    {
        try {
            $this->info('Starting Xray logs monitoring...');

            $this->logMonitorService->monitorAllPanels();

            $this->info('Xray logs monitoring completed successfully');
            return 0;

        } catch (\Exception $e) {
            $this->error('Xray logs monitoring failed: ' . $e->getMessage());
            return 1;
        }
    }
}
