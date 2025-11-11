<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Server\Server;
use App\Dto\Server\ServerFactory;
use App\Services\Panel\marzban\MarzbanService;

class DebugLogs extends Command
{
    protected $signature = 'vpn:debug-logs
                            {server? : Specific server host}
                            {--window=10 : Time window in minutes}';

    protected $description = 'Debug VPN logs to see what data is available';

    private MarzbanService $marzbanService;

    public function __construct(MarzbanService $marzbanService)
    {
        parent::__construct();
        $this->marzbanService = $marzbanService;
    }

    public function handle()
    {
        $serverHost = $this->argument('server');
        $window = $this->option('window');

//        $servers = Server::where('server_status', Server::SERVER_CONFIGURED)->get();

        $servers = $serverHost
            ? Server::where('host', $serverHost)->where('server_status', Server::SERVER_CONFIGURED)->get()
            : Server::where('server_status', Server::SERVER_CONFIGURED)->get();

        if ($servers->isEmpty()) {
            $this->error('No configured servers found');
            return;
        }

        foreach ($servers as $server) {
            $this->info("\n🔍 Debugging server: {$server->host}");
            $this->debugServerLogs($server, $window);
        }
    }

    private function debugServerLogs(Server $server, int $windowMinutes): void
    {
        try {
            $serverDto = ServerFactory::fromEntity($server);
            $ssh = $this->marzbanService->connectSshAdapter($serverDto);

            // 1. Проверим существование файла
            $this->checkFileExists($ssh, $server);

            // 2. Проверим размер файла и количество строк
            $this->checkFileStats($ssh, $server);

            // 3. Проверим последние записи в логе
            $this->checkRecentEntries($ssh, $server, $windowMinutes);

            // 4. Проверим конкретно наши целевые записи
            $this->checkTargetEntries($ssh, $server);

        } catch (\Exception $e) {
            $this->error("❌ Error debugging server {$server->host}: {$e->getMessage()}");
        }
    }

    private function checkFileExists($ssh, Server $server): void
    {
        $this->info("📁 Checking log file existence...");

        $commands = [
            'default' => 'ls -la /var/lib/marzban/access.log',
            'alternative1' => 'ls -la /opt/marzban/access.log',
            'alternative2' => 'find /var/lib /opt /home -name "access.log" 2>/dev/null | head -5',
            'marzban_dir' => 'ls -la /var/lib/marzban/',
            'log_files' => 'find / -name "*access*log*" -type f 2>/dev/null | grep marzban | head -10'
        ];

        foreach ($commands as $name => $command) {
            $result = trim($ssh->exec($command));
            if (!empty($result) && !str_contains($result, 'No such file')) {
                $this->line("✅ [{$name}] {$command}");
                $this->line("   Result: {$result}");

                if ($name === 'default' || $name === 'alternative1') {
                    return; // Нашли основной файл
                }
            }
        }

        $this->error("❌ Log file not found in common locations");
    }

    private function checkFileStats($ssh, Server $server): void
    {
        $this->info("📊 Checking log file stats...");

        $commands = [
            'size' => 'stat -c "%s" /var/lib/marzban/access.log 2>/dev/null || echo "NOT_FOUND"',
            'lines' => 'wc -l /var/lib/marzban/access.log 2>/dev/null || echo "NOT_FOUND"',
            'modified' => 'stat -c "%y" /var/lib/marzban/access.log 2>/dev/null || echo "NOT_FOUND"',
        ];

        foreach ($commands as $name => $command) {
            $result = trim($ssh->exec($command));
            $this->line("   {$name}: {$result}");
        }
    }

    private function checkRecentEntries($ssh, Server $server, int $windowMinutes): void
    {
        $this->info("🕒 Checking recent log entries...");

        // Проверим последние 20 строк лога
        $command = "tail -20 /var/lib/marzban/access.log";
        $result = $ssh->exec($command);

        if (empty(trim($result))) {
            $this->error("   ❌ No recent entries found");
            return;
        }

        $this->line("   Recent entries:");
        $lines = explode("\n", trim($result));
        foreach ($lines as $line) {
            $this->line("   📄 {$line}");
        }
    }

    private function checkTargetEntries($ssh, Server $server): void
    {
        $this->info("🎯 Checking for target entries (accepted + email)...");

        $commands = [
            'recent_accepted' => "tail -100 /var/lib/marzban/access.log | grep -a 'accepted' | grep -a 'email:' | head -10",
            'count_accepted' => "tail -1000 /var/lib/marzban/access.log | grep -c 'accepted.*email:'",
            'sample_emails' => "tail -500 /var/lib/marzban/access.log | grep -a 'email:' | awk '{print \$NF}' | sort -u | head -5",
        ];

        foreach ($commands as $name => $command) {
            $result = trim($ssh->exec($command));
            $this->line("   {$name}: {$result}");

            if ($name === 'recent_accepted' && !empty($result)) {
                $this->line("   Sample accepted entries:");
                $lines = explode("\n", trim($result));
                foreach (array_slice($lines, 0, 3) as $line) {
                    $this->line("   ✅ {$line}");
                }
            }
        }
    }
}
