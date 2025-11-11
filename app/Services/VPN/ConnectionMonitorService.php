<?php

namespace App\Services\VPN;

use App\Models\VPN\ConnectionLimitViolation;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Server\Server;
use App\Dto\Server\ServerFactory;
use App\Services\Panel\marzban\MarzbanService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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
     * Умный мониторинг - баланс скорости и качества данных
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
                // Пробуем разные методы по очереди до получения данных
                $serverResults = $this->analyzeWithFallback($server, $threshold, $windowMinutes);
                $results['violations_found'] += $serverResults['violations_count'];
                $results['servers_checked'][] = [
                    'server_id' => $server->id,
                    'host' => $server->host,
                    'violations' => $serverResults['violations_count'],
                    'users_checked' => $serverResults['users_checked'],
                    'lines_processed' => $serverResults['lines_processed'],
                    'processing_time' => $serverResults['processing_time'],
                    'method_used' => $serverResults['method_used'],
                    'data_quality' => $serverResults['data_quality']
                ];

                Log::info('Smart monitoring completed', [
                    'server_id' => $server->id,
                    'method' => $serverResults['method_used'],
                    'quality' => $serverResults['data_quality'],
                    'violations' => $serverResults['violations_count']
                ]);

            } catch (\Exception $e) {
                $errorMsg = "Server {$server->host}: {$e->getMessage()}";
                $results['errors'][] = $errorMsg;
                Log::error('Smart monitoring failed', ['server_id' => $server->id, 'error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    /**
     * Анализ с fallback методами
     */
    private function analyzeWithFallback(Server $server, int $threshold, int $windowMinutes): array
    {
        $methods = [
            'fast_tail' => 'analyzeFastTail',      // Самый быстрый
            'time_based' => 'analyzeTimeBased',    // Более точный
            'extended' => 'analyzeExtended'        // Полные данные
        ];

        foreach ($methods as $methodName => $method) {
            try {
                $startTime = microtime(true);
                $result = $this->{$method}($server, $threshold, $windowMinutes);
                $result['processing_time'] = round(microtime(true) - $startTime, 2);
                $result['method_used'] = $methodName;

                // Если нашли данные достаточного качества - возвращаем
                if ($result['data_quality'] >= 0.7 || $result['users_checked'] > 0) {
                    Log::info("Method {$methodName} succeeded", [
                        'server' => $server->host,
                        'quality' => $result['data_quality'],
                        'users' => $result['users_checked']
                    ]);
                    return $result;
                }
            } catch (\Exception $e) {
                Log::warning("Method {$methodName} failed, trying next", [
                    'server' => $server->host,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        throw new \Exception("All analysis methods failed for server {$server->host}");
    }

    /**
     * Метод 1: Быстрый анализ последних строк (качество: 0.8)
     */
    private function analyzeFastTail(Server $server, int $threshold, int $windowMinutes): array
    {
        $serverDto = ServerFactory::fromEntity($server);
        $ssh = $this->marzbanService->connectSshAdapter($serverDto);

        // Эмпирически: ~100 подключений в минуту × окно + запас
        $linesToRead = $windowMinutes * 150;

        $command = "tail -n {$linesToRead} /var/lib/marzban/access.log | " .
            "grep -a 'accepted.*email:' | " .
            "awk '{
                ip = \$4;
                gsub(/:[0-9]*\$/, \"\", ip);
                user_id = \$(NF-1);
                gsub(/email:/, \"\", user_id);
                print user_id \" \" ip;
            }'";

        $output = $ssh->exec($command);
        $userConnections = $this->parseSimpleLogData($output);

        $violations = $this->detectViolations($userConnections, $threshold, $server);

        return [
            'violations_count' => $violations['count'],
            'users_checked' => count($userConnections),
            'lines_processed' => $violations['total_connections'],
            'data_quality' => 0.8 // Хорошее качество для текущих нарушений
        ];
    }

    /**
     * Метод 2: Временной анализ (качество: 0.9)
     */
    private function analyzeTimeBased(Server $server, int $threshold, int $windowMinutes): array
    {
        $serverDto = ServerFactory::fromEntity($server);
        $ssh = $this->marzbanService->connectSshAdapter($serverDto);

        // Формируем временной диапазон
        $startTime = date('Y/m/d H:i:s', strtotime("-$windowMinutes minutes"));
        $endTime = date('Y/m/d H:i:s');

        $command = "grep -a 'accepted' /var/lib/marzban/access.log | " .
            "grep -a 'email:' | " .
            "awk '\$1\" \"\$2 >= \"$startTime\" {
                ip = \$4; gsub(/:[0-9]*\$/, \"\", ip);
                user_id = \$(NF-1); gsub(/email:/, \"\", user_id);
                print user_id \" \" ip;
            }' | " .
            "head -n 5000"; // Ограничиваем для скорости

        $output = $ssh->exec($command);
        $userConnections = $this->parseSimpleLogData($output);

        $violations = $this->detectViolations($userConnections, $threshold, $server);

        return [
            'violations_count' => $violations['count'],
            'users_checked' => count($userConnections),
            'lines_processed' => $violations['total_connections'],
            'data_quality' => 0.9 // Высокое качество
        ];
    }

    /**
     * Метод 3: Расширенный анализ (качество: 1.0)
     */
    private function analyzeExtended(Server $server, int $threshold, int $windowMinutes): array
    {
        $serverDto = ServerFactory::fromEntity($server);
        $ssh = $this->marzbanService->connectSshAdapter($serverDto);

        // Используем временные метки для точного окна
        $cutoffTime = time() - ($windowMinutes * 60);

        $command = "grep -a 'accepted' /var/lib/marzban/access.log | " .
            "grep -a 'email:' | " .
            "awk '{
                # Парсим дату/время в timestamp
                date_time = \$1 \" \" \$2;
                gsub(/\\//, \"-\", date_time);
                cmd = \"date -d \\\"\" date_time \"\\\" +%s 2>/dev/null\";
                cmd | getline timestamp;
                close(cmd);

                if (timestamp >= $cutoffTime) {
                    ip = \$4; gsub(/:[0-9]*\$/, \"\", ip);
                    user_id = \$(NF-1); gsub(/email:/, \"\", user_id);
                    print user_id \" \" ip;
                }
            }'";

        $output = $ssh->exec($command);
        $userConnections = $this->parseSimpleLogData($output);

        $violations = $this->detectViolations($userConnections, $threshold, $server);

        return [
            'violations_count' => $violations['count'],
            'users_checked' => count($userConnections),
            'lines_processed' => $violations['total_connections'],
            'data_quality' => 1.0 // Идеальное качество
        ];
    }

    /**
     * Обнаружение нарушений
     */
    private function detectViolations(array $userConnections, int $threshold, Server $server): array
    {
        $violationsCount = 0;
        $totalConnections = 0;

        foreach ($userConnections as $userId => $connectionData) {
            $uniqueIps = $connectionData['unique_ips'];
            $ipCount = count($uniqueIps);
            $totalConnections += $ipCount;

            if ($ipCount > $threshold) {
                Log::warning("Violation detected", [
                    'user_id' => $userId,
                    'unique_ips' => $ipCount,
                    'threshold' => $threshold,
                    'ip_addresses' => $uniqueIps
                ]);

                $violationCreated = $this->handleUserViolation($userId, $ipCount, $uniqueIps, $server);
                if ($violationCreated) {
                    $violationsCount++;
                }
            }
        }

        return [
            'count' => $violationsCount,
            'total_connections' => $totalConnections
        ];
    }

    /**
     * Парсинг логов
     */
    private function parseSimpleLogData(string $output): array
    {
        $userConnections = [];
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            if (empty($line)) continue;

            $parts = explode(' ', trim($line));
            if (count($parts) < 2) continue;

            $userId = trim($parts[0]);
            $clientIp = trim($parts[1]);

            if (empty($userId) || empty($clientIp)) continue;

            if (!isset($userConnections[$userId])) {
                $userConnections[$userId] = ['unique_ips' => []];
            }

            $userConnections[$userId]['unique_ips'][$clientIp] = true;
        }

        foreach ($userConnections as &$data) {
            $data['unique_ips'] = array_keys($data['unique_ips']);
        }

        return $userConnections;
    }

    /**
     * Парсинг данных с временными метками
     */
    private function parseRecentLogData(string $output, int $windowMinutes): array
    {
        $userConnections = [];
        $lines = explode("\n", trim($output));
        $cutoffTime = time() - ($windowMinutes * 60);

        foreach ($lines as $line) {
            if (empty($line)) continue;

            $parts = explode(' ', trim($line));
            if (count($parts) < 3) continue;

            $timestamp = (int)$parts[0];
            $userId = $parts[1];
            $clientIp = $parts[2];

            // Дополнительная проверка времени
            if ($timestamp < $cutoffTime) {
                continue;
            }

            if (!isset($userConnections[$userId])) {
                $userConnections[$userId] = ['unique_ips' => []];
            }

            $userConnections[$userId]['unique_ips'][$clientIp] = true;
        }

        // Преобразуем IP-адреса в массивы
        foreach ($userConnections as &$data) {
            $data['unique_ips'] = array_keys($data['unique_ips']);
        }

        return $userConnections;
    }

    /**
     * Подсчет обработанных строк
     */
    private function countProcessedLines(array $userConnections): int
    {
        $count = 0;
        foreach ($userConnections as $data) {
            $count += count($data['unique_ips']);
        }
        return $count;
    }

    /**
     * Обработка нарушения (без изменений)
     */
    private function handleUserViolation(string $userId, int $ipCount, array $ipAddresses, Server $server): bool
    {
        try {
            $keyActivate = $this->findKeyActivateByUserId($userId);

            if (!$keyActivate) {
                Log::warning('KeyActivate not found for user', [
                    'user_id' => $userId
                ]);
                return false;
            }

            $existingViolation = ConnectionLimitViolation::where([
                'key_activate_id' => $keyActivate->id,
                'status' => ConnectionLimitViolation::STATUS_ACTIVE
            ])->first();

            if ($existingViolation) {
                Log::info('User already has active violation, skipping', [
                    'user_id' => $userId,
                    'violation_id' => $existingViolation->id
                ]);
                return false;
            }

            $panel = $server->panels()->first();
            if (!$panel) {
                Log::warning('Panel not found for server', [
                    'server_id' => $server->id,
                    'user_id' => $userId
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
                'ip_addresses' => $ipAddresses
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
