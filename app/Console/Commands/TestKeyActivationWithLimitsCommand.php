<?php

namespace App\Console\Commands;

use App\Models\KeyActivate\KeyActivate;
use App\Services\Key\KeyActivateService;
use Illuminate\Console\Command;

class TestKeyActivationWithLimitsCommand extends Command
{
    protected $signature = 'vpn:test-activation {--panel-id=26}';
    protected $description = 'Test key activation with IP limits';

    private KeyActivateService $keyService;

    public function __construct(KeyActivateService $keyService)
    {
        parent::__construct();
        $this->keyService = $keyService;
    }

    public function handle()
    {
        $panelId = $this->option('panel-id');

        $this->info("Testing key activation with IP limits on panel {$panelId}");

        try {
            // Находим тестовый ключ для этой панели
            $testKey = KeyActivate::whereHas('packSalesman.salesman.panel', function($query) use ($panelId) {
                $query->where('id', $panelId);
            })->where('status', KeyActivate::PAID)->first();

            if (!$testKey) {
                $this->error("No test key found for panel {$panelId}");
                return 1;
            }

            $this->info("Found test key: {$testKey->id}");
            $this->info("Key status: {$testKey->status}");

            // Активируем ключ с тестовым Telegram ID
            $testTgId = rand(100000000, 999999999);
            $this->info("Activating with Telegram ID: {$testTgId}");

            $activatedKey = $this->keyService->activate($testKey, $testTgId);

            $this->info("✅ Key activated successfully!");
            $this->info("New status: {$activatedKey->status}");
            $this->info("User TG ID: {$activatedKey->user_tg_id}");

            // Проверяем, создался ли пользователь на панели
            if ($activatedKey->keyActivateUser && $activatedKey->keyActivateUser->serverUser) {
                $serverUser = $activatedKey->keyActivateUser->serverUser;
                $this->info("Server User ID: {$serverUser->id}");
                $this->info("Panel ID: {$serverUser->panel_id}");

                // Здесь можно добавить проверку конфигурации пользователя на панели
                $this->info("✅ User created on panel successfully");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Activation failed: " . $e->getMessage());
            return 1;
        }
    }
}
