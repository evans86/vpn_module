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
            $key = KeyActivate::where('id', $keyId)
                ->with(['packSalesman.salesman.panel', 'packSalesman.pack'])
                ->first();

            if (!$key) {
                $this->error("❌ Key not found: {$keyId}");
                return 1;
            }

            $this->info("✅ Key found!");
            $this->info("Key ID: {$key->id}");
            $this->info("Status: {$this->getStatusText($key->status)} ({$key->status})");
            $this->info("User TG ID: " . ($key->user_tg_id ?: 'Not activated'));

            if ($key->packSalesman) {
                if ($key->packSalesman->salesman && $key->packSalesman->salesman->panel) {
                    $this->info("Panel: {$key->packSalesman->salesman->panel->id} ({$key->packSalesman->salesman->panel->panel_adress})");
                }
                if ($key->packSalesman->pack) {
                    $this->info("Pack: {$key->packSalesman->pack->name} (Period: {$key->packSalesman->pack->period} days)");
                }
            }

            // Проверяем статус ключа
            $this->checkKeyStatus($key);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Проверяем статус ключа и предлагаем действия
     */
    private function checkKeyStatus(KeyActivate $key): void
    {
        switch ($key->status) {
            case KeyActivate::ACTIVE:
                $this->info("ℹ️ Key is already ACTIVATED");
                $this->info("Activated at: " . ($key->activated_at ? date('Y-m-d H:i:s', $key->activated_at) : 'N/A'));
                $this->info("Finish at: " . ($key->finish_at ? date('Y-m-d H:i:s', $key->finish_at) : 'N/A'));

                if ($key->keyActivateUser && $key->keyActivateUser->serverUser) {
                    $this->info("Server User ID: {$key->keyActivateUser->serverUser->id}");
                }
                break;

            case KeyActivate::PAID:
                $this->info("\n🧪 Key is PAID and ready for activation");
                $this->tryActivateKey($key);
                break;

            case KeyActivate::EXPIRED:
                $this->error("❌ Key is EXPIRED");
                $this->info("Deleted at: " . ($key->deleted_at ? date('Y-m-d H:i:s', $key->deleted_at) : 'N/A'));
                break;

            case KeyActivate::DELETED:
                $this->error("❌ Key is DELETED");
                break;

            default:
                $this->error("❌ Unknown key status: {$key->status}");
                break;
        }
    }

    /**
     * Пытаемся активировать ключ
     */
    private function tryActivateKey(KeyActivate $key): void
    {
        try {
            $testTgId = rand(100000000, 999999999);
            $this->info("Using test Telegram ID: {$testTgId}");

            $activatedKey = $this->keyService->activate($key, $testTgId);

            $this->info("✅ Activation successful!");
            $this->info("New Status: {$this->getStatusText($activatedKey->status)}");
            $this->info("User TG ID: {$activatedKey->user_tg_id}");
            $this->info("Activated at: " . ($activatedKey->activated_at ? date('Y-m-d H:i:s', $activatedKey->activated_at) : 'N/A'));
            $this->info("Finish at: " . ($activatedKey->finish_at ? date('Y-m-d H:i:s', $activatedKey->finish_at) : 'N/A'));

            if ($activatedKey->keyActivateUser && $activatedKey->keyActivateUser->serverUser) {
                $this->info("Server User ID: {$activatedKey->keyActivateUser->serverUser->id}");
                $this->info("Panel ID: {$activatedKey->keyActivateUser->serverUser->panel_id}");

                // Проверяем конфигурацию
                $links = json_decode($activatedKey->keyActivateUser->serverUser->keys, true);
                if ($links && count($links) > 0) {
                    $this->info("Configuration links: " . count($links));
                    $this->info("First link: " . substr($links[0], 0, 80) . "...");
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Activation failed: " . $e->getMessage());
        }
    }

    /**
     * Получаем текстовое представление статуса
     */
    private function getStatusText(int $status): string
    {
        $statuses = [
            KeyActivate::EXPIRED => 'EXPIRED',
            KeyActivate::ACTIVE => 'ACTIVE',
            KeyActivate::PAID => 'PAID',
            KeyActivate::DELETED => 'DELETED'
        ];

        return $statuses[$status] ?? 'UNKNOWN';
    }
}
