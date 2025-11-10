<?php

namespace App\Console\Commands;

use App\Models\Panel\Panel;
use App\Services\VPN\V2IPLimit\V2IPLimitService;
use Illuminate\Console\Command;

class EnableIPLimitsCommand extends Command
{
    protected $signature = 'vpn:enable-ip-limits {--panel-id=} {--test}';
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
        $testMode = $this->option('test');

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
            $this->info("\n🔧 Processing panel: {$panel->id} ({$panel->panel_adress})");

            // Проверяем базовую поддержку
            if (!$this->limitService->checkPanelSupport($panel)) {
                $this->error("❌ Panel {$panel->id} doesn't support configuration");
                continue;
            }

            if ($testMode) {
                // Только тестовый режим
                $this->info("🧪 Testing user creation...");
                if ($this->limitService->testUserCreation($panel)) {
                    $this->info("✅ Test passed for panel {$panel->id}");
                } else {
                    $this->error("❌ Test failed for panel {$panel->id}");
                }
            } else {
                // Режим применения конфигурации
                $this->info("⚙️ Applying IP limit configuration...");
                if ($this->limitService->enableIPLimitForPanel($panel)) {
                    $this->info("✅ IP limits enabled for panel {$panel->id}");

                    // Тестируем создание пользователя
                    $this->info("🧪 Testing configuration...");
                    if ($this->limitService->testUserCreation($panel)) {
                        $this->info("✅ Configuration test passed");
                    } else {
                        $this->warn("⚠️ Configuration test failed, but limits may still work");
                    }
                } else {
                    $this->error("❌ Failed to enable IP limits for panel {$panel->id}");
                }
            }
        }

        $this->info("\n🎉 IP limits configuration completed");
        return 0;
    }
}
