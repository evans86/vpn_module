<?php

namespace App\Console\Commands;

use App\Models\Panel\Panel;
use App\Services\VPN\V2IPLimit\V2IPLimitService;
use Illuminate\Console\Command;

class EnableIPLimitsCommand extends Command
{
    protected $signature = 'vpn:enable-ip-limits {--panel-id=}';
    protected $description = 'Enable IP limits for Marzban panels';

    private V2IPLimitService $limitService;

    public function __construct(V2IPLimitService $limitService)
    {
        parent::__construct();
        $this->limitService = $limitService;
    }

    public function handle()
    {
        $panelId = $this->option('panel-id');

        if ($panelId) {
            $panel = Panel::find($panelId);
            if (!$panel) {
                $this->error("Panel {$panelId} not found");
                return 1;
            }
            $panels = collect([$panel]);
        } else {
            $panels = Panel::where('panel_status', Panel::PANEL_CONFIGURED)->get();
        }

        $this->info("Enabling IP limits for {$panels->count()} panels...");

        foreach ($panels as $panel) {
            $this->info("Processing panel: {$panel->id} ({$panel->panel_adress})");

            if ($this->limitService->checkPolicySupport($panel)) {
                if ($this->limitService->enableIPLimitForPanel($panel)) {
                    $this->info("✅ IP limits enabled for panel {$panel->id}");
                } else {
                    $this->error("❌ Failed to enable IP limits for panel {$panel->id}");
                }
            } else {
                $this->warn("⚠️ Policy not supported for panel {$panel->id}");
            }
        }

        $this->info("IP limits configuration completed");
        return 0;
    }
}
