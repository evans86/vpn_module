<?php

namespace App\Services\VPN;

use App\Models\VPN\ConnectionLimitViolation;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Server\Server;
use App\Dto\Server\ServerFactory;
use App\Services\Panel\marzban\MarzbanService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

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
     * Мониторинг подключений в скользящем окне 10 минут
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
                $serverResults = $this->analyzeServerLogsSlidingWindow($server, $threshold, $windowMinutes);
                $results['violations_found'] += $serverResults['violations_count'];
                $results['servers_checked'][] = [
                    'server_id' => $server->id,
                    'host' => $server->host,
                    'violations' => $serverResults['violations_count'],
                    'users_checked' => $serverResults['users_checked'],
                    'time_window' => "{$windowMinutes}min"
                ];

                Log::info('Sliding window monitoring completed', [
                    'server_id' => $server->id,
                    'violations_found' => $serverResults['violations_count'],
                    'users_checked' => $serverResults['users_checked'],
                    'window_minutes' => $windowMinutes
                ]);

            } catch (\Exception $e) {
                $errorMsg = "Server {$server->host}: {$e->getMessage()}";
                $results['errors'][] = $errorMsg;

                Log::error('Sliding window monitoring failed', [
                    'server_id' => $server->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Анализ логов в скользящем окне
     */
    private function analyzeServerLogsSlidingWindow(Server $server, int $threshold, int $windowMinutes): array
    {
        $violationsCount = 0;
        $usersChecked = 0;

        try {
            $serverDto = ServerFactory::fromEntity($server);
            $ssh = $this->marzbanService->connectSshAdapter($serverDto);

            // Получаем данные за последние 10 минут с группировкой по 1-минутным интервалам
            $userConnections = $this->getSlidingWindowData($ssh, $windowMinutes);
            $usersChecked = count($userConnections);

            Log::info("Sliding window analysis for server {$server->host}", [
                'users_count' => $usersChecked,
                'window_minutes' => $windowMinutes,
                'sample_users' => array_slice(array_keys($userConnections), 0, 3)
            ]);

            // Анализируем подключения каждого пользователя в скользящем окне
            foreach ($userConnections as $userId => $timeSlots) {
                $maxUniqueIps = $this->calculateMaxUniqueIpsInWindow($timeSlots, $windowMinutes);

                Log::debug("User sliding window analysis", [
                    'user_id' => $userId,
                    'max_unique_ips' => $maxUniqueIps,
                    'time_slots_count' => count($timeSlots)
                ]);

                if ($maxUniqueIps > $threshold) {
                    $ipAddresses = $this->getIpsForViolation($timeSlots, $windowMinutes);

                    Log::warning("🚨 SLIDING WINDOW VIOLATION detected", [
                        'user_id' => $userId,
                        'max_unique_ips' => $maxUniqueIps,
                        'threshold' => $threshold,
                        'window_minutes' => $windowMinutes,
                        'ip_addresses' => $ipAddresses
                    ]);

                    $violationCreated = $this->handleUserViolation($userId, $maxUniqueIps, $ipAddresses, $server);
                    if ($violationCreated) {
                        $violationsCount++;
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error("Error in sliding window analysis for server {$server->host}", [
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
     * Получение данных для скользящего окна
     */
    private function getSlidingWindowData($ssh, int $windowMinutes): array
    {
        $logPath = '/var/lib/marzban/access.log';

        // Команда для получения данных за последние N минут с группировкой по минутам
        $command = "grep -a 'accepted' {$logPath} " .
            "| grep -a 'email:' " .
            "| awk '{\$1=\$1; print \$1 \" \" \$2 \" \" \$4 \" \" \$(NF-1)}' " .
            "| sed 's/email://g; s/:[0-9]*\$//' " .
            "| awk '{
                # Парсим дату и время
                date_time = \$1 \" \" \$2;
                gsub(/\\//, \"-\", date_time);
                cmd = \"date -d \\\"\" date_time \"\\\" +%s 2>/dev/null\";
                cmd | getline timestamp;
                close(cmd);

                # Округляем до минуты
                time_slot = int(timestamp/60) * 60;

                # UUID пользователя (последнее поле перед email)
                user_id = \$NF;

                # IP адрес (третье поле)
                ip = \$3;

                print time_slot \" \" user_id \" \" ip;
            }' " .
            "| sort -n";

        $output = $ssh->exec($command);
        return $this->parseSlidingWindowData($output, $windowMinutes);
    }

    /**
     * Парсинг данных для скользящего окна
     */
    private function parseSlidingWindowData(string $output, int $windowMinutes): array
    {
        $userConnections = [];
        $lines = explode("\n", trim($output));

        // Текущее время (последняя временная метка в логе)
        $currentTime = time();
        $windowSeconds = $windowMinutes * 60;

        foreach ($lines as $line) {
            if (empty($line)) continue;

            $parts = explode(' ', trim($line));
            if (count($parts) < 3) continue;

            $timestamp = (int)$parts[0];
            $userId = $parts[1];
            $clientIp = $parts[2];

            // Пропускаем записи старше нашего окна анализа
            if ($currentTime - $timestamp > $windowSeconds + 300) { // +5 минут буфер
                continue;
            }

            // Группируем по пользователю и временному слоту (минута)
            if (!isset($userConnections[$userId])) {
                $userConnections[$userId] = [];
            }

            $timeSlot = $timestamp;
            if (!isset($userConnections[$userId][$timeSlot])) {
                $userConnections[$userId][$timeSlot] = [];
            }

            $userConnections[$userId][$timeSlot][$clientIp] = true;
        }

        return $userConnections;
    }

    /**
     * Расчет максимального количества уникальных IP в скользящем окне
     */
    private function calculateMaxUniqueIpsInWindow(array $timeSlots, int $windowMinutes): int
    {
        $maxUniqueIps = 0;
        $windowSeconds = $windowMinutes * 60;

        // Сортируем временные слоты
        ksort($timeSlots);
        $timeSlots = array_slice($timeSlots, -20); // Берем последние 20 минут для анализа

        if (empty($timeSlots)) {
            return 0;
        }

        // Анализируем скользящее окно
        $timeKeys = array_keys($timeSlots);
        $startIndex = 0;

        for ($endIndex = 0; $endIndex < count($timeKeys); $endIndex++) {
            $endTime = $timeKeys[$endIndex];

            // Сдвигаем начало окна, если нужно
            while ($startIndex <= $endIndex && ($endTime - $timeKeys[$startIndex]) > $windowSeconds) {
                $startIndex++;
            }

            // Считаем уникальные IP в текущем окне
            $uniqueIps = [];
            for ($i = $startIndex; $i <= $endIndex; $i++) {
                $slotTime = $timeKeys[$i];
                $ipsInSlot = array_keys($timeSlots[$slotTime]);
                $uniqueIps = array_merge($uniqueIps, $ipsInSlot);
            }

            $uniqueIps = array_unique($uniqueIps);
            $maxUniqueIps = max($maxUniqueIps, count($uniqueIps));
        }

        return $maxUniqueIps;
    }

    /**
     * Получение IP адресов для нарушения (за последние N минут нарушения)
     */
    private function getIpsForViolation(array $timeSlots, int $windowMinutes): array
    {
        $windowSeconds = $windowMinutes * 60;
        ksort($timeSlots);

        // Берем последние временные слоты в пределах окна
        $recentSlots = array_slice($timeSlots, -10, null, true);
        $ipAddresses = [];

        foreach ($recentSlots as $ips) {
            $ipAddresses = array_merge($ipAddresses, array_keys($ips));
        }

        return array_unique($ipAddresses);
    }

    /**
     * Обработка нарушения
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

            Log::info('New sliding window violation recorded', [
                'user_id' => $userId,
                'unique_ips' => $ipCount,
                'ip_addresses' => $ipAddresses
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to handle user violation in sliding window', [
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

        // Статистика по последним нарушениям
        $recentViolations = ConnectionLimitViolation::with(['keyActivate', 'serverUser'])
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return [
            'total_violations' => $totalViolations,
            'active_violations' => $activeViolations,
            'today_violations' => $todayViolations,
            'servers_count' => $serversCount,
            'recent_violations' => $recentViolations,
            'monitoring_period' => 'sliding_window_10min'
        ];
    }

    /**
     * Альтернативный метод для ежедневного мониторинга (сохраняем для обратной совместимости)
     */
    public function monitorDailyConnections(int $threshold = 3): array
    {
        // Перенаправляем на новый метод с окном 24 часа (1440 минут)
        return $this->monitorSlidingWindow($threshold, 1440);
    }

    /**
     * Получение детальной статистики по серверам
     */
    public function getServerStats(): array
    {
        $servers = Server::where('server_status', 'configured')->get();
        $serverStats = [];

        foreach ($servers as $server) {
            $violationsCount = ConnectionLimitViolation::whereHas('panel', function ($query) use ($server) {
                $query->where('server_id', $server->id);
            })->where('status', ConnectionLimitViolation::STATUS_ACTIVE)->count();

            $serverStats[] = [
                'server_id' => $server->id,
                'host' => $server->host,
                'active_violations' => $violationsCount,
                'status' => $server->server_status
            ];
        }

        return $serverStats;
    }

    /**
     * Очистка старых нарушений (старше 30 дней)
     */
    public function cleanupOldViolations(int $days = 30): int
    {
        $deleted = ConnectionLimitViolation::where('created_at', '<', now()->subDays($days))
            ->where('status', ConnectionLimitViolation::STATUS_RESOLVED)
            ->delete();

        Log::info("Cleaned up old connection violations", [
            'deleted_count' => $deleted,
            'older_than_days' => $days
        ]);

        return $deleted;
    }
}
