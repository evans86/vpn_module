<?php

namespace App\Console\Commands;

use App\Models\KeyActivate\KeyActivate;
use App\Services\Key\KeyActivateService;
use Illuminate\Console\Command;

class TestSpecificKeyCommand extends Command
{
    protected $signature = 'vpn:test-key {key}';
    protected $description = 'Test activation of specific key with IP limits';

    private KeyActivateService $keyService;

    public function __construct(KeyActivateService $keyService)
    {
        parent::__construct();
        $this->keyService = $keyService;
    }

    public function handle()
    {
        $keyId = $this->argument('key');

        $this->info("Testing key: {$keyId}");

        try {
            // Находим ключ
            $key = KeyActivate::where('id', $keyId)->with(['packSalesman.salesman.panel'])->first();

            if (!$key) {
                $this->error("❌ Key not found: {$keyId}");
                return 1;
            }

            $this->info("✅ Key found!");
            $this->info("Key ID: {$key->id}");
            $this->info("Status: {$key->status}");
            $this->info("User TG ID: " . ($key->user_tg_id ?: 'Not activated'));

            if ($key->packSalesman && $key->packSalesman->salesman && $key->packSalesman->salesman->panel) {
                $this->info("Panel: {$key->packSalesman->salesman->panel->id} ({$key->packSalesman->salesman->panel->panel_adress})");
                $this->info("Salesman: {$key->packSalesman->salesman->id}");
            }

            // Если ключ уже активирован
            if ($key->status === KeyActivate::ACTIVE) {
                $this->info("ℹ️ Key is already activated");
                $this->info("Activated at: " . ($key->activated_at ? date('Y-m-d H:i:s', $key->activated_at) : 'N/A'));

                if ($key->keyActivateUser && $key->keyActivateUser->serverUser) {
                    $this->info("Server User ID: {$key->keyActivateUser->serverUser->id}");
                }

                return 0;
            }

            // Если ключ не активирован - тестируем активацию
            if ($key->status === KeyActivate::PAID) {
                $this->info("\n🧪 Testing activation...");

                $testTgId = rand(100000000, 999999999);
                $this->info("Using test Telegram ID: {$testTgId}");

                $activatedKey = $this->keyService->activate($key, $testTgId);

                $this->info("✅ Activation successful!");
                $this->info("New Status: {$activatedKey->status}");
                $this->info("User TG ID: {$activatedKey->user_tg_id}");

                if ($activatedKey->keyActivateUser && $activatedKey->keyActivateUser->serverUser) {
                    $this->info("Server User ID: {$activatedKey->keyActivateUser->serverUser->id}");
                    $this->info("Panel ID: {$activatedKey->keyActivateUser->serverUser->panel_id}");

                    // Проверяем конфигурацию
                    $links = json_decode($activatedKey->keyActivateUser->serverUser->keys, true);
                    if ($links && count($links) > 0) {
                        $this->info("Configuration links: " . count($links));
                        $this->info("First link: " . substr($links[0], 0, 50) . "...");
                    }
                }

                return 0;
            }

            $this->error("❌ Key has invalid status for activation: {$key->status}");
            return 1;

        } catch (\Exception $e) {
            $this->error("❌ Activation failed: " . $e->getMessage());
            return 1;
        }
    }
}
