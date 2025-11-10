<?php

namespace App\Console\Commands;

use App\Dto\Server\ServerFactory;
use App\Models\KeyActivate\KeyActivate;
use App\Services\Panel\marzban\MarzbanService;
use Illuminate\Console\Command;

class CheckXrayLogsCommand extends Command
{
    protected $signature = 'vpn:check-logs {key}';
    protected $description = 'Check Xray logs for connection limit events';

    public function handle()
    {
        $keyId = $this->argument('key');

        $this->info("Checking logs for key: {$keyId}");

        try {
            $key = KeyActivate::where('id', $keyId)
                ->with(['keyActivateUser.serverUser.panel.server'])
                ->first();

            if (!$key || !$key->keyActivateUser || !$key->keyActivateUser->serverUser) {
                $this->error("Key or user not found");
                return 1;
            }

            $serverUser = $key->keyActivateUser->serverUser;
            $panel = $serverUser->panel;
            $server = $panel->server;

            $this->info("Panel: {$panel->id}");
            $this->info("Server: {$server->ip}");
            $this->info("User: {$serverUser->id}");

            $marzbanService = app(MarzbanService::class);

            $serverDto = ServerFactory::fromEntity($server);
            // Получаем логи через SSH
            $ssh = $marzbanService->connectSshAdapter($server);

            $commands = [
                // Ищем логи ограничений подключений
                'sudo journalctl -u xray -n 20 --no-pager | grep -i "limit\\|connection\\|' . $serverUser->id . '"',
                'sudo tail -20 /var/log/xray/error.log 2>/dev/null | grep -i "limit\\|connection"',
                'docker logs marzban-xray --tail 20 2>/dev/null | grep -i "limit\\|connection"'
            ];

            $this->info("\n📋 Checking logs for connection limit events...");

            $foundLogs = false;
            foreach ($commands as $command) {
                $output = $ssh->exec($command);
                if (!empty(trim($output))) {
                    $this->info("Logs found:");
                    $this->info($output);
                    $foundLogs = true;
                    break;
                }
            }

            if (!$foundLogs) {
                $this->info("No connection limit logs found (this is normal if no limits were exceeded)");
            }

            $this->info("\n🔍 To manually test limits:");
            $this->info("1. Connect with the same config on 4 devices simultaneously");
            $this->info("2. Check logs again: php artisan vpn:check-logs {$keyId}");
            $this->info("3. Look for 'limit exceeded' or 'connection rejected' messages");

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }
    }
}
