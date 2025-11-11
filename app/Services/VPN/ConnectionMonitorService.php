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
     * Быстрый мониторинг подключений за последние 10 минут
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
                $serverResults = $this->analyzeRecentLogs($server, $threshold, $windowMinutes);
                $results['violations_found'] += $serverResults['violations_count'];
                $results['servers_checked'][] = [
                    'server_id' => $server->id,
                    'host' => $server->host,
                    'violations' => $serverResults['violations_count'],
                    'users_checked' => $serverResults['users_checked'],
                    'lines_processed' => $serverResults['lines_processed'],
                    'processing_time' => $serverResults['processing_time'],
                    'time_window' => "{$windowMinutes}min"
                ];

                Log::info('Fast sliding window monitoring completed', [
                    'server_id' => $server->id,
                    'violations_found' => $serverResults['violations_count'],
                    'users_checked' => $serverResults['users_checked'],
                    'lines_processed' => $serverResults['lines_processed'],
                    'processing_time' => $serverResults['processing_time']
                ]);

            } catch (\Exception $e) {
                $errorMsg = "Server {$server->host}: {$e->getMessage()}";
                $results['errors'][] = $errorMsg;

                Log::error('Fast sliding window monitoring failed', [
                    'server_id' => $server->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Быстрый анализ только последних записей лога
     */
    private function analyzeRecentLogs(Server $server, int $threshold, int $windowMinutes): array
    {
        $startTime = microtime(true);
        $violationsCount = 0;
        $usersChecked = 0;
        $linesProcessed = 0;

        try {
            $serverDto = ServerFactory::fromEntity($server);
            $ssh = $this->marzbanService->connectSshAdapter($serverDto);

            // Получаем только последние записи (примерно последние 10-15 минут)
            $userConnections = $this->getRecentLogData($ssh, $windowMinutes);
            $usersChecked = count($userConnections);
            $linesProcessed = $this->countProcessedLines($userConnections);

            Log::info("Fast analysis for server {$server->host}", [
                'users_count' => $usersChecked,
                'lines_processed' => $linesProcessed,
                'window_minutes' => $windowMinutes
            ]);

            // Анализируем подключения каждого пользователя
            foreach ($userConnections as $userId => $connectionData) {
                $uniqueIps = $connectionData['unique_ips'];
                $ipCount = count($uniqueIps);

                if ($ipCount > $threshold) {
                    Log::warning("🚨 FAST VIOLATION detected", [
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

        } catch (\Exception $e) {
            Log::error("Error in fast analysis for server {$server->host}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        $processingTime = round(microtime(true) - $startTime, 2);

        return [
            'violations_count' => $violationsCount,
            'users_checked' => $usersChecked,
            'lines_processed' => $linesProcessed,
            'processing_time' => $processingTime
        ];
    }

    /**
     * Получение только последних данных из лога (оптимизированно)
     */
    private function getRecentLogData($ssh, int $windowMinutes): array
    {
        $logPath = '/var/lib/marzban/access.log';

        // Вычисляем временную метку для отсечения старых записей
        $cutoffTime = time() - ($windowMinutes * 60);

        // Команда которая читает лог с КОНЦА и останавливается когда находит старые записи
        $command = "tail -n 10000 {$logPath} | " . // Берем только последние 10000 строк
            "tac | " . // Переворачиваем чтобы читать с конца
            "awk '/accepted.*email:/ {
                # Парсим дату и время
                date_time = \$1 \" \" \$2;
                gsub(/\\//, \"-\", date_time);
                cmd = \"date -d \\\"\" date_time \"\\\" +%s 2>/dev/null\";
                cmd | getline timestamp;
                close(cmd);

                # Если запись старше нашего окна - выходим
                if (timestamp < $cutoffTime) exit;

                # UUID пользователя
                user_id = \$(NF-1);
                gsub(/email:/, \"\", user_id);

                # IP адрес (без порта)
                ip = \$4;
                gsub(/:[0-9]*\$/, \"\", ip);

                print timestamp \" \" user_id \" \" ip;
            }' | " .
            "tac"; // Возвращаем в нормальный порядок

        $output = $ssh->exec($command);
        return $this->parseRecentLogData($output, $windowMinutes);
    }

    /**
     * Альтернативный метод - используем grep для поиска по времени
     */
    private function getRecentLogDataAlternative($ssh, int $windowMinutes): array
    {
        $logPath = '/var/lib/marzban/access.log';

        // Получаем текущее время и время начала окна
        $currentTime = date('Y/m/d H:i:s');
        $startTime = date('Y/m/d H:i:s', strtotime("-$windowMinutes minutes"));

        // Команда использует grep для поиска записей за последние N минут
        $command = "grep -a 'accepted' {$logPath} | " .
            "grep -a 'email:' | " .
            "awk '\$1\" \"\$2 >= \"$startTime\" && \$1\" \"\$2 <= \"$currentTime\" { " .
            "ip = \$4; gsub(/:[0-9]*\$/, \"\", ip); " .
            "user_id = \$(NF-1); gsub(/email:/, \"\", user_id); " .
            "print user_id \" \" ip; }'";

        $output = $ssh->exec($command);
        return $this->parseSimpleLogData($output);
    }

    /**
     * Самый простой и быстрый метод - берем только последние N строк
     */
    private function getRecentLogDataSimple($ssh, int $windowMinutes): array
    {
        $logPath = '/var/lib/marzban/access.log';

        // Эмпирически определяем сколько строк примерно соответствует 10-15 минутам
        $estimatedLines = $windowMinutes * 100; // ~100 строк в минуту

        // Берем в 2 раза больше на всякий случай
        $linesToRead = $estimatedLines * 2;

        $command = "tail -n {$linesToRead} {$logPath} | " .
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
        return $this->parseSimpleLogData($output);
    }

    /**
     * Парсинг упрощенных данных лога
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

        // Преобразуем IP-адреса в массивы
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
