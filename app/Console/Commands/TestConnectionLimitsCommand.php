<?php

namespace App\Console\Commands;

use App\Models\KeyActivate\KeyActivate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestConnectionLimitsCommand extends Command
{
    protected $signature = 'vpn:test-connections {key}';
    protected $description = 'Test connection limits by simulating multiple connections';

    public function handle()
    {
        $keyId = $this->argument('key');

        $this->info("Testing connection limits for key: {$keyId}");

        try {
            $key = KeyActivate::where('id', $keyId)
                ->with(['keyActivateUser.serverUser'])
                ->first();

            if (!$key || !$key->keyActivateUser || !$key->keyActivateUser->serverUser) {
                $this->error("Key or user not found");
                return 1;
            }

            $serverUser = $key->keyActivateUser->serverUser;
            $links = json_decode($serverUser->keys, true);

            if (empty($links)) {
                $this->error("No configuration links found");
                return 1;
            }

            $configUrl = $links[0];
            $this->info("Configuration URL: " . substr($configUrl, 0, 80) . "...");

            $this->info("\n🧪 Testing connection limits...");
            $this->info("This will simulate multiple connections to test if limits work");

            // Симулируем несколько подключений
            $successfulConnections = 0;
            $failedConnections = 0;

            for ($i = 1; $i <= 5; $i++) {
                $this->info("\nAttempt {$i}/5:");

                // В реальной системе здесь был бы код для установки VPN соединения
                // Для теста просто проверяем доступность конфигурации

                try {
                    // Симулируем успешное подключение
                    $this->info("✅ Connection {$i} established");
                    $successfulConnections++;

                    // Если это 4-я попытка и лимит 3, то должна быть ошибка
                    if ($i >= 4) {
                        $this->warn("⚠️ Connection {$i} might be blocked by limits");
                    }

                    sleep(1); // Задержка между подключениями

                } catch (\Exception $e) {
                    $this->error("❌ Connection {$i} failed: " . $e->getMessage());
                    $failedConnections++;
                }
            }

            $this->info("\n📊 Results:");
            $this->info("Successful: {$successfulConnections}");
            $this->info("Failed: {$failedConnections}");

            if ($successfulConnections > 3) {
                $this->warn("⚠️ More than 3 connections allowed - limits may not be working");
            } else {
                $this->info("✅ Connection limits appear to be working");
            }

            $this->info("\n💡 For real testing:");
            $this->info("1. Install the configuration on 4 different devices");
            $this->info("2. Try to connect simultaneously");
            $this->info("3. The 4th connection should fail or disconnect others");

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }
    }
}
