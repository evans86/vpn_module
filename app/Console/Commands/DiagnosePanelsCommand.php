<?php

namespace App\Console\Commands;

use App\Models\Panel\Panel;
use App\Services\VPN\V2IPLimit\V2IPLimitService;
use Illuminate\Console\Command;

class DiagnosePanelsCommand extends Command
{
    protected $signature = 'vpn:diagnose-panels';
    protected $description = 'Show current panel configurations';

    private V2IPLimitService $limitService;

    public function __construct(V2IPLimitService $limitService)
    {
        parent::__construct();
        $this->limitService = $limitService;
    }

    public function handle()
    {
        $panels = Panel::where('panel_status', Panel::PANEL_CONFIGURED)->get();

        foreach ($panels as $panel) {
            $this->info("\n=== Panel {$panel->id} ===");
            $this->info("Address: {$panel->panel_adress}");

            try {
                $config = $this->limitService->getCurrentConfig($panel);

                $this->info("Has policy: " . (isset($config['policy']) ? 'YES' : 'NO'));
                $this->info("Has inbounds: " . (isset($config['inbounds']) ? 'YES' : 'NO'));

                if (isset($config['inbounds'])) {
                    $this->info("Inbounds count: " . count($config['inbounds']));
                    foreach ($config['inbounds'] as $inbound) {
                        $this->info("  - {$inbound['tag']} ({$inbound['protocol']})");
                    }
                }

            } catch (\Exception $e) {
                $this->error("Error: " . $e->getMessage());
            }
        }

        return 0;
    }
}
