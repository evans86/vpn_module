<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Server\Server;
use App\Dto\Server\ServerFactory;
use App\Services\Panel\marzban\MarzbanService;

class DebugRealTime extends Command
{
    protected $signature = 'vpn:debug-realtime
                            {server=vpnserver1737486248nl.vpn-telegram.com : Server host}
                            {--window=10 : Time window in minutes}';

    protected $description = 'Real-time debug of log parsing';

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

        $server = Server::where('host', $serverHost)->firstOrFail();

        $this->info("🔍 Real-time debug for: {$server->host}");
        $this->info("⏰ Time window: {$window} minutes");

        $serverDto = ServerFactory::fromEntity($server);
        $ssh = $this->marzbanService->connectSshAdapter($serverDto);

        // 1. Узнаем текущее время на сервере
        $this->checkServerTime($ssh);

        // 2. Проверим сколько строк соответствует нашему окну
        $this->checkWindowData($ssh, $window);

        // 3. Проверим нашу команду шаг за шагом
        $this->testCommandStepByStep($ssh, $window);
    }

    private function checkServerTime($ssh): void
    {
        $this->info("\n🕒 Server time check:");

        $commands = [
            'current_time' => 'date',
            'time_10min_ago' => 'date -d "10 minutes ago"',
            'time_1hour_ago' => 'date -d "1 hour ago"',
            'time_24hour_ago' => 'date -d "24 hours ago"',
        ];

        foreach ($commands as $name => $command) {
            $result = trim($ssh->exec($command));
            $this->line("   {$name}: {$result}");
        }
    }

    private function checkWindowData($ssh, int $windowMinutes): void
    {
        $this->info("\n📊 Data volume check:");

        // Вычисляем временную границу
        $timeThreshold = trim($ssh->exec("date -d '{$windowMinutes} minutes ago' '+%Y/%m/%d %H:%M:%S'"));

        $this->line("   Time threshold: {$timeThreshold}");

        // Проверяем количество строк за этот период
        $countCommand = "awk '\$1\" \"\$2 >= \"$timeThreshold\"' /var/lib/marzban/access.log | wc -l";
        $lineCount = trim($ssh->exec($countCommand));

        $this->line("   Lines in {$windowMinutes}min window: {$lineCount}");

        // Проверяем количество accepted записей
        $acceptedCountCommand = "awk '\$1\" \"\$2 >= \"$timeThreshold\"' /var/lib/marzban/access.log | grep -c 'accepted.*email:'";
        $acceptedCount = trim($ssh->exec($acceptedCountCommand));

        $this->line("   Accepted entries in window: {$acceptedCount}");

        // Проверяем уникальных пользователей
        $usersCommand = "awk '\$1\" \"\$2 >= \"$timeThreshold\"' /var/lib/marzban/access.log | grep 'accepted.*email:' | awk '{print \$(NF-1)}' | sed 's/email://' | sort -u | wc -l";
        $usersCount = trim($ssh->exec($usersCommand));

        $this->line("   Unique users in window: {$usersCount}");
    }

    private function testCommandStepByStep($ssh, int $windowMinutes): void
    {
        $this->info("\n🔧 Testing our command step by step:");

        // 1. Сначала проверим tail с разными лимитами
        $this->info("   1. Testing tail with different limits:");

        $tailLimits = [100, 1000, 5000, 10000, 50000];
        foreach ($tailLimits as $limit) {
            $testCommand = "tail -n {$limit} /var/lib/marzban/access.log | grep -c 'accepted.*email:'";
            $count = trim($ssh->exec($testCommand));
            $this->line("      tail -{$limit}: {$count} accepted entries");
        }

        // 2. Проверим нашу финальную команду
        $this->info("   2. Testing final parsing command:");

        $linesToRead = $windowMinutes * 500; // Увеличиваем значительно
        $finalCommand = "tail -n {$linesToRead} /var/lib/marzban/access.log | " .
            "grep 'accepted' | " .
            "grep 'email:' | " .
            "awk '{
                           ip = \$4;
                           gsub(/:[0-9]*\$/, \"\", ip);
                           user_id = \$(NF-1);
                           gsub(/email:/, \"\", user_id);
                           print user_id \" \" ip;
                       }' | head -20"; // Ограничиваем вывод

        $output = $ssh->exec($finalCommand);

        $this->line("   Final command output (first 20 lines):");
        $lines = explode("\n", trim($output));
        foreach ($lines as $i => $line) {
            $this->line("      {$i}: {$line}");
        }

        // 3. Проверим есть ли пользователи с >2 IP
        $this->info("   3. Checking for users with multiple IPs:");

        $analysisCommand = "tail -n {$linesToRead} /var/lib/marzban/access.log | " .
            "grep 'accepted' | " .
            "grep 'email:' | " .
            "awk '{
                              ip = \$4;
                              gsub(/:[0-9]*\$/, \"\", ip);
                              user_id = \$(NF-1);
                              gsub(/email:/, \"\", user_id);
                              print user_id \" \" ip;
                          }' | " .
            "awk '{
                              ips[\$1][\$2]++;
                          }
                          END {
                              for (user in ips) {
                                  count = 0;
                                  for (ip in ips[user]) count++;
                                  if (count > 2) {
                                      print \"USER: \" user \" - \" count \" unique IPs\";
                                      for (ip in ips[user]) {
                                          print \"  IP: \" ip;
                                      }
                                  }
                              }
                          }'";

        $analysisOutput = $ssh->exec($analysisCommand);

        if (empty(trim($analysisOutput))) {
            $this->line("      No users with >2 IPs found");
        } else {
            $this->line("      Users with violations:");
            $this->line($analysisOutput);
        }
    }
}
