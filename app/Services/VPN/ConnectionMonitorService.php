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
     * Упрощенный и надежный мониторинг
     */
    public function monitorSlidingWindow(int $threshold = 2, int $windowMinutes = 10): array
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
                $serverResults = $this->analyzeServerLogsSimple($server, $threshold, $windowMinutes);
                $results['violations_found'] += $serverResults['violations_count'];
                $results['servers_checked'][] = [
                    'server_id' => $server->id,
                    'host' => $server->host,
                    'violations' => $serverResults['violations_count'],
                    'users_checked' => $serverResults['users_checked'],
                    'unique_ips_total' => $serverResults['unique_ips_total'],
                    'processing_time' => $serverResults['processing_time']
                ];

                Log::info('Monitoring completed for server', [
                    'server' => $server->host,
                    'violations' => $serverResults['violations_count'],
                    'users' => $serverResults['users_checked'],
                    'total_ips' => $serverResults['unique_ips_total']
                ]);

            } catch (\Exception $e) {
                $errorMsg = "Server {$server->host}: {$e->getMessage()}";
                $results['errors'][] = $errorMsg;
                Log::error('Monitoring failed', ['server' => $server->host, 'error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    /**
     * Простой и надежный анализ
     */
    private function analyzeServerLogsSimple(Server $server, int $threshold, int $windowMinutes): array
    {
        $startTime = microtime(true);

        $serverDto = ServerFactory::fromEntity($server);
        $ssh = $this->marzbanService->connectSshAdapter($serverDto);

        // Упрощенная команда - берем последние N строк
        $linesToRead = $windowMinutes * 100; // ~100 строк в минуту

        $command = "tail -n {$linesToRead} /var/lib/marzban/access.log | " .
            "grep -a 'accepted' | " .
            "grep -a 'email:' | " .
            "awk '{
                       ip = \$4;
                       gsub(/:[0-9]*\$/, \"\", ip);
                       user_id = \$(NF-1);
                       gsub(/email:/, \"\", user_id);
                       print user_id \" \" ip;
                   }'";

        $output = $ssh->exec($command);

        if (empty(trim($output))) {
            return [
                'violations_count' => 0,
                'users_checked' => 0,
                'unique_ips_total' => 0,
                'processing_time' => round(microtime(true) - $startTime, 2)
            ];
        }

        // Парсим данные
        $userConnections = [];
        $lines = explode("\n", trim($output));
        $linesProcessed = 0;

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            $parts = explode(' ', trim($line));
            if (count($parts) < 2) continue;

            $userId = trim($parts[0]);
            $clientIp = trim($parts[1]);

            if (empty($userId) || empty($clientIp)) continue;

            if (!isset($userConnections[$userId])) {
                $userConnections[$userId] = ['unique_ips' => []];
            }

            $userConnections[$userId]['unique_ips'][$clientIp] = true;
            $linesProcessed++;
        }

        // Анализируем нарушения
        $violationsCount = 0;
        $uniqueIpsTotal = 0;

        foreach ($userConnections as $userId => $connectionData) {
            $uniqueIps = array_keys($connectionData['unique_ips']);
            $ipCount = count($uniqueIps);
            $uniqueIpsTotal += $ipCount;

            Log::debug("User analysis", [
                'user_id' => $userId,
                'unique_ips_count' => $ipCount,
                'ips' => $uniqueIps
            ]);

            if ($ipCount > $threshold) {
                Log::warning("🚨 VIOLATION detected", [
                    'user_id' => $userId,
                    'unique_ips_count' => $ipCount,
                    'threshold' => $threshold
                ]);

                $violationCreated = $this->handleUserViolation($userId, $ipCount, $uniqueIps, $server);
                if ($violationCreated) {
                    $violationsCount++;
                }
            }
        }

        Log::info("Analysis summary", [
            'server' => $server->host,
            'users_checked' => count($userConnections),
            'total_connections' => $linesProcessed,
            'unique_ips_total' => $uniqueIpsTotal,
            'violations_found' => $violationsCount
        ]);

        return [
            'violations_count' => $violationsCount,
            'users_checked' => count($userConnections),
            'unique_ips_total' => $uniqueIpsTotal,
            'processing_time' => round(microtime(true) - $startTime, 2)
        ];
    }

    /**
     * Обработка нарушения
     */
    private function handleUserViolation(string $userId, int $ipCount, array $ipAddresses, Server $server): bool
    {
        try {
            // Убираем префикс если есть (типа "3782.") чтобы найти в базе
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

            // Проверяем активные нарушения
            $existingViolation = ConnectionLimitViolation::where([
                'key_activate_id' => $keyActivate->id,
                'status' => ConnectionLimitViolation::STATUS_ACTIVE
            ])->first();

            if ($existingViolation) {
                Log::info('User already has active violation, skipping', [
                    'user_id' => $userId
                ]);
                return false;
            }

            $panel = $server->panels()->first();
            if (!$panel) {
                Log::warning('Panel not found for server', [
                    'server_id' => $server->id
                ]);
                return false;
            }

            // Создаем нарушение
            $this->limitMonitorService->recordViolation(
                $keyActivate,
                $ipCount,
                $ipAddresses,
                $panel->id
            );

            Log::info('New violation recorded', [
                'user_id' => $userId,
                'unique_ips' => $ipCount
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to handle user violation', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Поиск KeyActivate по ID пользователя
     */
    private function findKeyActivateByUserId(string $userId): ?KeyActivate
    {
        return KeyActivate::whereHas('keyActivateUser.serverUser', function ($query) use ($userId) {
            $query->where('id', $userId);
        })->first();
    }

    /**
     * Получение статистики мониторинга
     */
    public function getMonitoringStats(): array
    {
        $totalViolations = ConnectionLimitViolation::count();
        $activeViolations = ConnectionLimitViolation::where('status', ConnectionLimitViolation::STATUS_ACTIVE)->count();
        $todayViolations = ConnectionLimitViolation::whereDate('created_at', today())->count();
        $serversCount = Server::where('server_status', 'configured')->count();

        return [
            'total_violations' => $totalViolations,
            'active_violations' => $activeViolations,
            'today_violations' => $todayViolations,
            'servers_count' => $serversCount,
        ];
    }
}
