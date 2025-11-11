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
     * Исправленный мониторинг с точным временным окном
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
                $serverResults = $this->analyzeServerLogsFixed($server, $threshold, $windowMinutes);
                $results['violations_found'] += $serverResults['violations_count'];
                $results['servers_checked'][] = [
                    'server_id' => $server->id,
                    'host' => $server->host,
                    'violations' => $serverResults['violations_count'],
                    'users_checked' => $serverResults['users_checked'],
                    'unique_ips_total' => $serverResults['unique_ips_total'],
                    'processing_time' => $serverResults['processing_time']
                ];

                Log::info('Fixed monitoring completed', [
                    'server_id' => $server->id,
                    'violations' => $serverResults['violations_count'],
                    'users' => $serverResults['users_checked']
                ]);

            } catch (\Exception $e) {
                $errorMsg = "Server {$server->host}: {$e->getMessage()}";
                $results['errors'][] = $errorMsg;
                Log::error('Fixed monitoring failed', ['server' => $server->host, 'error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    /**
     * Исправленный анализ с точным временным окном
     */
    private function analyzeServerLogsFixed(Server $server, int $threshold, int $windowMinutes): array
    {
        $startTime = microtime(true);

        $serverDto = ServerFactory::fromEntity($server);
        $ssh = $this->marzbanService->connectSshAdapter($serverDto);

        // Получаем данные за ТОЧНОЕ временное окно
        $userConnections = $this->getExactTimeWindowData($ssh, $windowMinutes);

        $violationsCount = 0;
        $uniqueIpsTotal = 0;

        foreach ($userConnections as $userId => $connectionData) {
            $uniqueIps = $connectionData['unique_ips'];
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
            'violations_count' => $violationsCount,
            'users_checked' => count($userConnections),
            'unique_ips_total' => $uniqueIpsTotal,
            'processing_time' => round(microtime(true) - $startTime, 2)
        ];
    }

    /**
     * Получение данных за точное временное окно
     */
    private function getExactTimeWindowData($ssh, int $windowMinutes): array
    {
        $logPath = '/var/lib/marzban/access.log';

        // Вычисляем временную границу на СЕРВЕРЕ
        $command = "date -d '{$windowMinutes} minutes ago' '+%Y/%m/%d %H:%M:%S'";
        $timeThreshold = trim($ssh->exec($command));

        Log::info("Time threshold for analysis", [
            'window_minutes' => $windowMinutes,
            'time_threshold' => $timeThreshold
        ]);

        // Команда для выборки данных за временное окно
        $analysisCommand = "awk '\$1\" \"\$2 >= \"$timeThreshold\"' {$logPath} | " .
            "grep -a 'accepted' | " .
            "grep -a 'email:' | " .
            "awk '{
                # Извлекаем IP (4-е поле, убираем порт)
                ip = \$4;
                gsub(/:[0-9]*\$/, \"\", ip);

                # Извлекаем email (предпоследнее поле)
                user_id = \$(NF-1);
                gsub(/email:/, \"\", user_id);

                print user_id \" \" ip;
            }'";

        $output = $ssh->exec($analysisCommand);

        Log::debug("Raw log analysis output", [
            'output_length' => strlen($output),
            'first_500_chars' => substr($output, 0, 500)
        ]);

        return $this->parseLogDataFixed($output);
    }

    /**
     * Альтернативный метод - используем tail + временную фильтрацию
     */
    private function getExactTimeWindowDataAlternative($ssh, int $windowMinutes): array
    {
        $logPath = '/var/lib/marzban/access.log';

        // Берем больше строк чтобы покрыть окно
        $estimatedLines = $windowMinutes * 200; // ~200 строк в минуту

        $command = "tail -n {$estimatedLines} {$logPath} | " .
            "grep -a 'accepted' | " .
            "grep -a 'email:' | " .
            "awk '{
                # Парсим дату/время в timestamp
                date_time = \$1 \" \" \$2;
                gsub(/\\//, \"-\", date_time);
                cmd = \"date -d \\\"\" date_time \"\\\" +%s 2>/dev/null\";
                cmd | getline timestamp;
                close(cmd);

                # Текущее время на сервере
                current_time = systime();

                # Фильтруем по временному окну
                if (current_time - timestamp <= {$windowMinutes} * 60) {
                    ip = \$4;
                    gsub(/:[0-9]*\$/, \"\", ip);
                    user_id = \$(NF-1);
                    gsub(/email:/, \"\", user_id);
                    print user_id \" \" ip;
                }
            }'";

        return $this->parseLogDataFixed($ssh->exec($command));
    }

    /**
     * Исправленный парсинг логов
     */
    private function parseLogDataFixed(string $output): array
    {
        $userConnections = [];
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            $parts = explode(' ', trim($line));
            if (count($parts) < 2) continue;

            $userId = trim($parts[0]);
            $clientIp = trim($parts[1]);

            // Валидация данных
            if (empty($userId) || empty($clientIp)) {
                continue;
            }

            // Проверяем формат UUID (может быть с префиксом типа "3782.")
            if (!preg_match('/[a-f0-9\-]+$/i', $userId)) {
                continue;
            }

            // Проверяем формат IP
            if (!filter_var($clientIp, FILTER_VALIDATE_IP)) {
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

        Log::info("Parsed user connections", [
            'total_users' => count($userConnections),
            'total_connections' => array_sum(array_map(fn($data) => count($data['unique_ips']), $userConnections)),
            'sample_users' => array_slice(array_keys($userConnections), 0, 5)
        ]);

        return $userConnections;
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
                'clean_user_id' => $cleanUserId,
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
