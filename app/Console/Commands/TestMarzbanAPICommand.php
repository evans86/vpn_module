<?php

namespace App\Console\Commands;

use App\Models\Panel\Panel;
use Illuminate\Console\Command;

class TestMarzbanAPICommand extends Command
{
    protected $signature = 'vpn:test-api {panel-id}';
    protected $description = 'Test Marzban API connectivity';

    public function handle()
    {
        $panel = Panel::find($this->argument('panel-id'));

        if (!$panel) {
            $this->error("Panel not found");
            return 1;
        }

        $this->info("Testing API for panel: {$panel->panel_adress}");

        try {
            // Тестируем получение токена
            $marzbanService = app(\App\Services\Panel\marzban\MarzbanService::class);
            $updatedPanel = $marzbanService->updateMarzbanToken($panel->id);

            $this->info("✅ Token obtained: " . substr($updatedPanel->auth_token, 0, 20) . '...');

            // Тестируем получение конфигурации
            $marzbanApi = new \App\Services\External\MarzbanAPI($panel->api_address);
            $config = $marzbanApi->getConfig($updatedPanel->auth_token);

            $this->info("✅ Config obtained");
            $this->info("Inbounds count: " . (isset($config['inbounds']) ? count($config['inbounds']) : 0));

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ API test failed: " . $e->getMessage());
            return 1;
        }
    }
}
