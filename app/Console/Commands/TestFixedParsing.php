<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Server\Server;
use App\Dto\Server\ServerFactory;
use App\Services\Panel\marzban\MarzbanService;

class TestFixedParsing extends Command
{
    protected $signature = 'vpn:test-fixed
                            {server=vpnserver1737486248nl.vpn-telegram.com : Server host}';

    protected $description = 'Test fixed parsing with detailed output';

    private MarzbanService $marzbanService;

    public function __construct(MarzbanService $marzbanService)
    {
        parent::__construct();
        $this->marzbanService = $marzbanService;
    }

    public function handle()
    {
        $serverHost = $this->argument('server');
        $server = Server::where('host', $serverHost)->firstOrFail();

        $this->info("🧪 Testing FIXED parsing for: {$server->host}");

        $serverDto = ServerFactory::fromEntity($server);
        $ssh = $this->marzbanService->connectSshAdapter($serverDto);

        // Тестируем исправленную команду
        $command = "tail -100 /var/lib/marzban/access.log | " .
            "grep -a 'accepted' | " .
            "grep -a 'email:' | " .
            "awk '{
                       ip = \$4;
                       gsub(/^(tcp:|udp:)/, \"\", ip);
                       gsub(/:[0-9]*\$/, \"\", ip);

                       for(i=1; i<=NF; i++) {
                           if (\$i == \"email:\") {
                               user_id = \$(i+1);
                               break;
                           }
                       }

                       print \"USER: \" user_id \" | IP: \" ip;
                   }' | head -20";

        $output = $ssh->exec($command);

        $this->info("📄 Fixed parsing results:");
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            $this->line("   {$line}");
        }

        // Проверим есть ли нарушения
        $analysisCommand = "tail -5000 /var/lib/marzban/access.log | " .
            "grep -a 'accepted' | " .
            "grep -a 'email:' | " .
            "awk '{
                              ip = \$4;
                              gsub(/^(tcp:|udp:)/, \"\", ip);
                              gsub(/:[0-9]*\$/, \"\", ip);

                              for(i=1; i<=NF; i++) {
                                  if (\$i == \"email:\") {
                                      user_id = \$(i+1);
                                      break;
                                  }
                              }

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
                                      print \"🚨 VIOLATION: \" user \" - \" count \" unique IPs\";
                                      for (ip in ips[user]) {
                                          print \"  📍 \" ip;
                                      }
                                      print \"\";
                                  }
                              }
                          }'";

        $analysisOutput = $ssh->exec($analysisCommand);

        if (empty(trim($analysisOutput))) {
            $this->info("✅ No violations found in sample");
        } else {
            $this->info("🚨 Violations found:");
            $this->line($analysisOutput);
        }
    }
}
