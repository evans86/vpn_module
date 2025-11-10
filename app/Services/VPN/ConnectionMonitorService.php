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

            // Получаем список всех подключений за 24 часа
            $logOutput = $ssh->exec($this->buildLogAnalysisCommand());
            $userConnections = $this->parseLogOutput($logOutput);
            $usersChecked = count($userConnections);

            // Анализируем подключения каждого пользователя
            foreach ($userConnections as $userId => $connectionData) {
                $uniqueIps = $connectionData['unique_ips'];
                $ipCount = count($uniqueIps);

                // Если уникальных IP больше лимита - нарушение
                if ($ipCount > $threshold) {
                    $violationCreated = $this->handleUserViolation($userId, $ipCount, $uniqueIps, $server);
                    if ($violationCreated) {
                        $violationsCount++;
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to analyze server logs', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
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

        // Вместо фильтра по конкретной дате, анализируем все доступные логи
        // Marzban логи обычно содержат последние подключения
        return "grep 'accepted' {$logPath} " .
            "| grep 'email:' " .
            "| awk '{ip=\$3; email=\$(NF-1); print email, ip}' " .
            "| sed 's/email://g' " .
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

            // Формат: user_uuid client_ip
            $parts = explode(' ', trim($line));
            if (count($parts) < 2) continue;

            $userId = trim($parts[0]);  // UUID пользователя
            $clientIp = trim($parts[1]); // IP-адрес клиента

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
