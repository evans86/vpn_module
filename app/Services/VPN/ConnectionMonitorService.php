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
    )
    {
        $this->marzbanService = $marzbanService;
        $this->limitMonitorService = $limitMonitorService;
    }

    /**
     * Мониторинг подключений по логам за последние 24 часа
     */
    public function monitorDailyConnections(int $threshold = 3): array
    {
        $servers = Server::where('server_status', Server::SERVER_CONFIGURED)->get();

        $results = [
            'total_servers' => $servers->count(), // Сразу устанавливаем реальное значение
            'violations_found' => 0,
            'servers_checked' => [],
            'errors' => []
        ];

        foreach ($servers as $server) {
            try {
                $serverResults = $this->analyzeServerLogs($server, $threshold);
                $results['violations_found'] += $serverResults['violations_count'];
                $results['servers_checked'][] = [
                    'server_id' => $server->id,
                    'host' => $server->host,
                    'violations' => $serverResults['violations_count'],
                    'users_checked' => $serverResults['users_checked']
                ];

                Log::info('Server connection monitoring completed', [
                    'server_id' => $server->id,
                    'violations_found' => $serverResults['violations_count'],
                    'users_checked' => $serverResults['users_checked']
                ]);

            } catch (\Exception $e) {
                $errorMsg = "Server {$server->host}: {$e->getMessage()}";
                $results['errors'][] = $errorMsg;

                Log::error('Server connection monitoring failed', [
                    'server_id' => $server->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Анализ логов сервера за последние 24 часа
     */
    private function analyzeServerLogs(Server $server, int $threshold): array
    {
        $violationsCount = 0;
        $usersChecked = 0;

        try {
            $serverDto = ServerFactory::fromEntity($server);
            $ssh = $this->marzbanService->connectSshAdapter($serverDto);

            // 1. Сначала проверим что файл существует и его размер
            $fileInfoCommand = "ls -la /var/lib/marzban/access.log";
            $fileInfo = $ssh->exec($fileInfoCommand);
            Log::info("File info for server {$server->host}", ['file_info' => $fileInfo]);

            // 2. Проверим количество строк в логе
            $lineCountCommand = "wc -l /var/lib/marzban/access.log";
            $lineCount = trim($ssh->exec($lineCountCommand));
            Log::info("Total lines in log for server {$server->host}", ['line_count' => $lineCount]);

            // 3. Проверим последние 5 строк лога чтобы увидеть формат
            $lastLinesCommand = "tail -5 /var/lib/marzban/access.log";
            $lastLines = $ssh->exec($lastLinesCommand);
            Log::info("Last 5 lines of log for server {$server->host}", ['last_lines' => $lastLines]);

            // 4. Проверим нашу команду на маленьком наборе данных (с -a)
            $testCommand = "grep -a 'accepted' /var/lib/marzban/access.log | grep -a 'email:' | head -10";
            $testOutput = $ssh->exec($testCommand);
            Log::info("Test command output for server {$server->host}", ['test_output' => $testOutput]);

            // 5. Проверим нашу команду анализа на этих 10 строках (с -a)
            $testAnalysisCommand = "grep -a 'accepted' /var/lib/marzban/access.log | grep -a 'email:' | head -10 | awk '{print \$(NF-1), \$4}' | sed 's/email://g; s/:[0-9]*\$//'";
            $testAnalysis = $ssh->exec($testAnalysisCommand);
            Log::info("Test analysis output for server {$server->host}", ['test_analysis' => $testAnalysis]);

            // 6. Только после этого запускаем основную команду (с -a)
            $command = $this->buildLogAnalysisCommand();
            $logOutput = $ssh->exec($command);

            Log::info("Main command output for server {$server->host}", [
                'output_length' => strlen($logOutput),
                'first_500_chars' => substr($logOutput, 0, 500)
            ]);

            if (empty(trim($logOutput))) {
                Log::warning("No data from main command for server {$server->host}");
                return [
                    'violations_count' => 0,
                    'users_checked' => 0
                ];
            }

            $userConnections = $this->parseLogOutput($logOutput);
            $usersChecked = count($userConnections);

            Log::info("Parsed users for server {$server->host}", [
                'users_count' => $usersChecked,
                'first_users' => array_slice(array_keys($userConnections), 0, 5)
            ]);

            // Анализируем подключения каждого пользователя
            foreach ($userConnections as $userId => $connectionData) {
                $uniqueIps = $connectionData['unique_ips'];
                $ipCount = count($uniqueIps);

                Log::info("User analysis for server {$server->host}", [
                    'user_id' => $userId,
                    'unique_ips_count' => $ipCount,
                    'ip_addresses' => $uniqueIps
                ]);

                if ($ipCount > $threshold) {
                    Log::warning("🚨 VIOLATION detected for server {$server->host}", [
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

        } catch (\Exception $e) {
            Log::error("Error analyzing server {$server->host}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

        return [
            'violations_count' => $violationsCount,
            'users_checked' => $usersChecked
        ];
    }

    /**
     * Построение команды для анализа логов за 24 часа
     * Извлекаем только UUID пользователя и IP-адрес
     */
    private function buildLogAnalysisCommand(): string
    {
        $logPath = '/var/lib/marzban/access.log';

        return "grep -a 'accepted' {$logPath} " .
            "| grep -a 'email:' " .
            "| awk '{print \$(NF-1), \$4}' " . // предпоследнее поле = email, 4 поле = IP
            "| sed 's/email://g; s/:[0-9]*\$//' " .
            "| sort | uniq";
    }

    /**
     * Парсинг вывода логов - группируем по UUID пользователя
     */
    private function parseLogOutput(string $logOutput): array
    {
        $userConnections = [];
        $lines = explode("\n", trim($logOutput));

        foreach ($lines as $line) {
            if (empty($line)) continue;

            // Формат: user_uuid client_ip (без порта)
            $parts = explode(' ', trim($line));
            if (count($parts) < 2) continue;

            $userId = trim($parts[0]);  // UUID пользователя
            $clientIp = trim($parts[1]); // IP-адрес клиента (уже без порта)

            if (!isset($userConnections[$userId])) {
                $userConnections[$userId] = [
                    'unique_ips' => []
                ];
            }

            // Добавляем уникальный IP
            $userConnections[$userId]['unique_ips'][$clientIp] = true;
        }

        // Преобразуем IP-адреса в массив
        foreach ($userConnections as &$data) {
            $data['unique_ips'] = array_keys($data['unique_ips']);
        }

        return $userConnections;
    }

    /**
     * Обработка нарушения - создаем только если нет активного нарушения
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

            // Проверяем, есть ли уже активное нарушение у этого пользователя
            $existingViolation = ConnectionLimitViolation::where([
                'key_activate_id' => $keyActivate->id,
                'status' => ConnectionLimitViolation::STATUS_ACTIVE
            ])->first();

            if ($existingViolation) {
                Log::info('User already has active violation, skipping', [
                    'user_id' => $userId,
                    'violation_id' => $existingViolation->id,
                    'current_ip_count' => $ipCount
                ]);
                return false;
            }

            // Получаем панель сервера
            $panel = $server->panels()->first();

            if (!$panel) {
                Log::warning('Panel not found for server', [
                    'server_id' => $server->id,
                    'user_id' => $userId
                ]);
                return false;
            }

            // Создаем новое нарушение
            $this->limitMonitorService->recordViolation(
                $keyActivate,
                $ipCount, // Количество уникальных IP = количество устройств
                $ipAddresses,
                $panel->id
            );

            Log::info('New connection limit violation recorded', [
                'user_id' => $userId,
                'unique_ips' => $ipCount,
                'ip_addresses' => $ipAddresses,
                'server_id' => $server->id
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
     * Поиск KeyActivate по ID пользователя (UUID из логов)
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

        return [
            'total_violations' => $totalViolations,
            'active_violations' => $activeViolations,
            'today_violations' => $todayViolations,
            'servers_count' => Server::where('server_status', 'configured')->count()
        ];
    }
}
