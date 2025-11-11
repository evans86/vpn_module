<?php

namespace App\Services\VPN;

use App\Models\VPN\ConnectionLimitViolation;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Server\Server;
use App\Dto\Server\ServerFactory;
use App\Services\Panel\marzban\MarzbanService;
use Illuminate\Support\Facades\Log;

class ConnectionMonitorService
{
    private MarzbanService $marzbanService;
    private ConnectionLimitMonitorService $limitMonitorService;

    public function __construct(
        MarzbanService                $marzbanService,
        ConnectionLimitMonitorService $limitMonitorService
    ) {
        $this->marzbanService = $marzbanService;
        $this->limitMonitorService = $limitMonitorService;
    }

    /**
     * ИСПРАВЛЕННЫЙ мониторинг
     */
    public function monitorFixed(int $threshold = 2, int $windowMinutes = 60): array
    {
        $servers = Server::where('server_status', Server::SERVER_CONFIGURED)->get();

        $results = [
            'total_servers' => $servers->count(),
            'violations_found' => 0,
            'servers_checked' => [],
            'errors' => []
        ];

        foreach ($servers as $server) {
            try {
                $serverResults = $this->analyzeServerLogsFixed($server, $threshold, $windowMinutes);
                $results['violations_found'] += $serverResults['violations_count'];
                $results['servers_checked'][] = [
                    'server_id' => $server->id,
                    'host' => $server->host,
                    'violations' => $serverResults['violations_count'],
                    'users_checked' => $serverResults['users_checked'],
                    'unique_ips_total' => $serverResults['unique_ips_total'],
                    'processing_time' => $serverResults['processing_time'],
                    'data_notes' => $serverResults['data_notes']
                ];

            } catch (\Exception $e) {
                $errorMsg = "Server {$server->host}: {$e->getMessage()}";
                $results['errors'][] = $errorMsg;
            }
        }

        return $results;
    }

    private function analyzeServerLogsFixed(Server $server, int $threshold, int $windowMinutes): array
    {
        $startTime = microtime(true);

        $serverDto = ServerFactory::fromEntity($server);
        $ssh = $this->marzbanService->connectSshAdapter($serverDto);

        // ФИКС 1: Используем grep с -a для бинарных файлов и берем больше данных
        $linesToRead = $windowMinutes * 200; // Увеличиваем лимит

        $command = "tail -n {$linesToRead} /var/lib/marzban/access.log | " .
            "grep -a 'accepted' | " .
            "grep -a 'email:' | " .
            "awk '{
                       # ФИКС 2: Правильно извлекаем IP (4-е поле, убираем tcp: udp: префиксы)
                       ip = \$4;
                       gsub(/^(tcp:|udp:)/, \"\", ip);  # Убираем префиксы
                       gsub(/:[0-9]*\$/, \"\", ip);     # Убираем порт

                       # ФИКС 3: Правильно извлекаем UserID (предпоследнее поле)
                       for(i=1; i<=NF; i++) {
                           if (\$i == \"email:\") {
                               user_id = \$(i+1);
                               break;
                           }
                       }

                       print user_id \" \" ip;
                   }'";

        $output = $ssh->exec($command);

        Log::info("Fixed monitoring raw output", [
            'server' => $server->host,
            'output_length' => strlen($output),
            'first_5_lines' => array_slice(explode("\n", $output), 0, 5)
        ]);

        if (empty(trim($output))) {
            return [
                'violations_count' => 0,
                'users_checked' => 0,
                'unique_ips_total' => 0,
                'processing_time' => round(microtime(true) - $startTime, 2),
                'data_notes' => 'No data found in logs'
            ];
        }

        // ФИКС 4: Правильный парсинг
        $userConnections = [];
        $lines = explode("\n", trim($output));
        $validLines = 0;

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            $parts = explode(' ', trim($line));
            if (count($parts) < 2) continue;

            $userId = trim($parts[0]);
            $clientIp = trim($parts[1]);

            // ФИКС 5: Валидация данных
            if (empty($userId) || $userId === 'tcp:' || $userId === 'udp:') {
                continue;
            }

            if (empty($clientIp) || !filter_var($clientIp, FILTER_VALIDATE_IP)) {
                continue;
            }

            if (!isset($userConnections[$userId])) {
                $userConnections[$userId] = ['unique_ips' => []];
            }

            $userConnections[$userId]['unique_ips'][$clientIp] = true;
            $validLines++;
        }

        // Анализируем нарушения
        $violationsCount = 0;
        $uniqueIpsTotal = 0;
        $violationsFound = [];

        foreach ($userConnections as $userId => $connectionData) {
            $uniqueIps = array_keys($connectionData['unique_ips']);
            $ipCount = count($uniqueIps);
            $uniqueIpsTotal += $ipCount;

            if ($ipCount > $threshold) {
                $violationsFound[] = [
                    'user_id' => $userId,
                    'ip_count' => $ipCount,
                    'ips' => $uniqueIps
                ];

                Log::warning("🚨 VIOLATION FOUND", [
                    'user_id' => $userId,
                    'unique_ips_count' => $ipCount,
                    'ip_addresses' => $uniqueIps
                ]);

                $violationCreated = $this->handleUserViolation($userId, $ipCount, $uniqueIps, $server);
                if ($violationCreated) {
                    $violationsCount++;
                }
            }
        }

        $dataNotes = "Processed {$validLines} lines, found " . count($userConnections) . " users";
        if (!empty($violationsFound)) {
            $dataNotes .= ", " . count($violationsFound) . " violations";
        }

        Log::info("Fixed monitoring results", [
            'server' => $server->host,
            'users_checked' => count($userConnections),
            'unique_ips_total' => $uniqueIpsTotal,
            'violations_found' => $violationsCount,
            'violations_details' => $violationsFound
        ]);

        return [
            'violations_count' => $violationsCount,
            'users_checked' => count($userConnections),
            'unique_ips_total' => $uniqueIpsTotal,
            'processing_time' => round(microtime(true) - $startTime, 2),
            'data_notes' => $dataNotes
        ];
    }

    private function handleUserViolation(string $userId, int $ipCount, array $ipAddresses, Server $server): bool
    {
        try {
            // Убираем префикс если есть
            $cleanUserId = $userId;
            if (preg_match('/\.([a-f0-9\-]+)$/i', $userId, $matches)) {
                $cleanUserId = $matches[1];
            }

            $keyActivate = $this->findKeyActivateByUserId($cleanUserId);

            if (!$keyActivate) {
                Log::warning('KeyActivate not found for user', [
                    'original_user_id' => $userId,
                    'clean_user_id' => $cleanUserId
                ]);
                return false;
            }

            $existingViolation = ConnectionLimitViolation::where([
                'key_activate_id' => $keyActivate->id,
                'status' => ConnectionLimitViolation::STATUS_ACTIVE
            ])->first();

            if ($existingViolation) {
                Log::info('User already has active violation, skipping', ['user_id' => $userId]);
                return false;
            }

            // ФИКС: Используем правильное отношение panel() вместо panels()
            $panel = $server->panel;
            if (!$panel) {
                Log::warning('Panel not found for server', [
                    'server_id' => $server->id,
                    'server_host' => $server->host
                ]);
                return false;
            }

            $this->limitMonitorService->recordViolation(
                $keyActivate,
                $ipCount,
                $ipAddresses,
                $panel->id
            );

            Log::info('New violation recorded', [
                'user_id' => $userId,
                'unique_ips' => $ipCount,
                'panel_id' => $panel->id,
                'server_id' => $server->id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to handle user violation', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString() // Добавим trace для диагностики
            ]);
            return false;
        }
    }

    private function findKeyActivateByUserId(string $userId): ?KeyActivate
    {
        return KeyActivate::whereHas('keyActivateUser.serverUser', function ($query) use ($userId) {
            $query->where('id', $userId);
        })->first();
    }
}
