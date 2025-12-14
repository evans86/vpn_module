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
     * ИСПРАВЛЕННЫЙ мониторинг с новой логикой нарушений
     */
    public function monitorFixed(int $threshold = 3, int $windowMinutes = 15): array
    {
        $servers = Server::where('server_status', Server::SERVER_CONFIGURED)->get();

        $results = [
            'total_servers' => $servers->count(),
            'violations_found' => 0,
            'servers_checked' => [],
            'errors' => []
        ];

        // Собираем данные со всех серверов сначала
        $allUsersData = [];
        foreach ($servers as $server) {
            try {
                $serverUsersData = $this->getServerUsersData($server, $windowMinutes);
                
                // Правильное объединение данных пользователей с разных серверов
                foreach ($serverUsersData as $userId => $userData) {
                    if (!isset($allUsersData[$userId])) {
                        $allUsersData[$userId] = [
                            'unique_ips' => [],
                            'servers' => [],
                            'ip_networks' => []
                        ];
                    }
                    
                    // Объединяем уникальные IP
                    if (isset($userData['unique_ips'])) {
                        $allUsersData[$userId]['unique_ips'] = array_merge(
                            $allUsersData[$userId]['unique_ips'],
                            $userData['unique_ips']
                        );
                    }
                    
                    // Объединяем серверы
                    if (isset($userData['servers'])) {
                        $allUsersData[$userId]['servers'] = array_merge(
                            $allUsersData[$userId]['servers'],
                            $userData['servers']
                        );
                    }
                    
                    // Объединяем сети IP
                    if (isset($userData['ip_networks'])) {
                        $allUsersData[$userId]['ip_networks'] = array_merge(
                            $allUsersData[$userId]['ip_networks'],
                            $userData['ip_networks']
                        );
                    }
                }

                $results['servers_checked'][] = [
                    'server_id' => $server->id,
                    'host' => $server->host,
                    'users_count' => count($serverUsersData),
                    'processing_time' => 0, // будет заполнено позже
                    'data_notes' => 'Data collected'
                ];

            } catch (\Exception $e) {
                $errorMsg = "Server {$server->host}: {$e->getMessage()}";
                $results['errors'][] = $errorMsg;
                Log::error('Ошибка при получении данных с сервера', [
                    'server_host' => $server->host,
                    'server_id' => $server->id,
                    'source' => 'vpn',
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Теперь анализируем собранные данные с новой логикой
        $violationsCount = $this->analyzeUsersWithNewLogic($allUsersData, $threshold);
        $results['violations_found'] = $violationsCount;

        return $results;
    }

    /**
     * Получить данные пользователей с сервера
     */
    private function getServerUsersData(Server $server, int $windowMinutes): array
    {
        $serverDto = ServerFactory::fromEntity($server);
        $ssh = $this->marzbanService->connectSshAdapter($serverDto);

        $linesToRead = $windowMinutes * 200;
        $command = "tail -n {$linesToRead} /var/lib/marzban/access.log | " .
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

                       print user_id \" \" ip \" \" \"{$server->host}\";
                   }'";

        $output = $ssh->exec($command);
        $usersData = [];

        if (empty(trim($output))) {
            return $usersData;
        }

        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            $parts = explode(' ', trim($line));
            if (count($parts) < 3) continue;

            $userId = trim($parts[0]);
            $clientIp = trim($parts[1]);
            $serverHost = trim($parts[2]);

            if (empty($userId) || $userId === 'tcp:' || $userId === 'udp:' ||
                empty($clientIp) || !filter_var($clientIp, FILTER_VALIDATE_IP)) {
                continue;
            }

            if (!isset($usersData[$userId])) {
                $usersData[$userId] = [
                    'unique_ips' => [],
                    'servers' => [],
                    'ip_networks' => []
                ];
            }

            $usersData[$userId]['unique_ips'][$clientIp] = true;
            $usersData[$userId]['servers'][$serverHost] = true;

            // Определяем сеть IP (первые 3 октета для IPv4)
            $ipParts = explode('.', $clientIp);
            if (count($ipParts) === 4) {
                $network = $ipParts[0] . '.' . $ipParts[1] . '.' . $ipParts[2] . '.0/24';
                $usersData[$userId]['ip_networks'][$network] = true;
            }
        }

        return $usersData;
    }

    /**
     * Новая логика анализа нарушений
     */
    private function analyzeUsersWithNewLogic(array $allUsersData, int $threshold): int
    {
        $violationsCount = 0;
        $usersChecked = 0;
        $usersWithMultipleIPs = 0;

        foreach ($allUsersData as $userId => $userData) {
            $usersChecked++;
            $uniqueIps = array_keys($userData['unique_ips']);
            $ipCount = count($uniqueIps);
            $networkCount = count($userData['ip_networks']);
            $serverCount = count($userData['servers']);

            // Логируем только пользователей с множественными IP (потенциальные нарушения)
            if ($ipCount > $threshold) {
                $usersWithMultipleIPs++;
                // User with multiple IPs detected (potential violation)
            }

            // НОВАЯ ЛОГИКА: Нарушение только если разные сети И превышен порог
            $isViolation = $this->isRealViolation($uniqueIps, $ipCount, $threshold);

            if ($isViolation) {
                Log::warning("🚨 REAL VIOLATION FOUND", [
                    'user_id' => $userId,
                    'unique_ips_count' => $ipCount,
                    'source' => 'vpn',
                    'unique_networks_count' => $networkCount,
                    'ip_addresses' => $uniqueIps,
                    'threshold' => $threshold,
                    'violation_reason' => 'Multiple networks detected'
                ]);

                $violationCreated = $this->handleUserViolation($userId, $ipCount, $uniqueIps);
                if ($violationCreated) {
                    $violationsCount++;
                    Log::info('Violation successfully recorded', [
                        'user_id' => $userId,
                        'violations_count' => $violationsCount,
                        'source' => 'vpn'
                    ]);
                } else {
                    Log::warning('Violation detection failed - handleUserViolation returned false', [
                        'user_id' => $userId,
                        'unique_ips_count' => $ipCount,
                        'source' => 'vpn'
                    ]);
                }
            } else {
                // Логируем почему нарушение не обнаружено (только для отладки, если превышен порог)
                if ($ipCount > $threshold) {
                    Log::debug('Potential violation skipped - all IPs from same network', [
                        'user_id' => $userId,
                        'unique_ips_count' => $ipCount,
                        'unique_networks_count' => $networkCount,
                        'threshold' => $threshold,
                        'source' => 'vpn'
                    ]);
                }
            }
        }

        // Логируем общую статистику анализа
        Log::info('Анализ пользователей завершен', [
            'total_users_checked' => $usersChecked,
            'users_with_multiple_ips' => $usersWithMultipleIPs,
            'violations_found' => $violationsCount,
            'threshold' => $threshold
        ]);

        return $violationsCount;
    }

    /**
     * Определяем настоящее ли это нарушение
     */
    private function isRealViolation(array $ipAddresses, int $ipCount, int $threshold): bool
    {
        // Если IP меньше или равно порогу - не нарушение
        if ($ipCount <= $threshold) {
            return false;
        }

        // Анализируем сети IP-адресов
        $networks = [];
        foreach ($ipAddresses as $ip) {
            $network = $this->getIPNetwork($ip);
            $networks[$network] = true;
        }

        $networkCount = count($networks);

        // Логируем анализ сетей для всех случаев превышения порога
        Log::info('Network analysis for potential violation', [
            'ip_count' => $ipCount,
            'network_count' => $networkCount,
            'threshold' => $threshold,
            'networks' => array_keys($networks),
            'ips' => $ipAddresses,
            'is_violation' => $networkCount > 1,
            'source' => 'vpn'
        ]);

        // НАША ЛОГИКА:
        // Нарушение только если есть IP из РАЗНЫХ сетей И превышен порог
        // Если все IP из одной сети (/24) - это не нарушение (пользователь в одной локации)
        // Это позволяет избежать ложных срабатываний для пользователей с несколькими устройствами в одной сети
        return $networkCount > 1;
    }

    /**
     * Получить сеть IP (/24 для IPv4)
     */
    private function getIPNetwork(string $ip): string
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        // Для IPv4 - берем первые 3 октета
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
            }
        }

        // Для IPv6 можно добавить свою логику
        return $ip;
    }

    /**
     * Обработка нарушения (упрощенная версия)
     */
    private function handleUserViolation(string $userId, int $ipCount, array $ipAddresses): bool
    {
        try {
            $cleanUserId = $userId;
            if (preg_match('/\.([a-f0-9\-]+)$/i', $userId, $matches)) {
                $cleanUserId = $matches[1];
            }

            $keyActivate = $this->findKeyActivateByUserId($cleanUserId);

            if (!$keyActivate) {
                Log::error('KeyActivate not found for user', [
                    'original_user_id' => $userId,
                    'clean_user_id' => $cleanUserId,
                    'source' => 'vpn',
                ]);
                return false;
            }

            // ВАЖНО: Проверяем, что ключ активен перед фиксацией нарушения
            // Если ключ был перевыпущен (статус EXPIRED), нарушения не должны фиксироваться
            // Приводим статус к int для корректного сравнения (может быть строкой из БД)
            $keyStatus = (int)$keyActivate->status;
            if ($keyStatus !== \App\Models\KeyActivate\KeyActivate::ACTIVE) {
                Log::info('Пропущена фиксация нарушения - ключ не активен', [
                    'key_id' => $keyActivate->id,
                    'key_status' => $keyActivate->status,
                    'key_status_type' => gettype($keyActivate->status),
                    'key_status_int' => $keyStatus,
                    'expected_status' => \App\Models\KeyActivate\KeyActivate::ACTIVE,
                    'source' => 'vpn',
                    'user_id' => $userId
                ]);
                return false;
            }

            // Создаем или обновляем нарушение (логика в recordViolation)
            // recordViolation автоматически увеличит счетчик если нарушение уже существует
            $this->limitMonitorService->recordViolation(
                $keyActivate,
                $ipCount,
                $ipAddresses,
                null // panel_id будет определен в сервисе
            );

            Log::info('New REAL violation recorded', [
                'user_id' => $userId,
                'unique_ips' => $ipCount,
                'source' => 'vpn',
                'ip_networks' => $this->getUniqueNetworks($ipAddresses)
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to handle user violation', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'vpn'
            ]);
            return false;
        }
    }

    /**
     * Получить уникальные сети из списка IP
     */
    private function getUniqueNetworks(array $ipAddresses): array
    {
        $networks = [];
        foreach ($ipAddresses as $ip) {
            $networks[$this->getIPNetwork($ip)] = true;
        }
        return array_keys($networks);
    }

    private function findKeyActivateByUserId(string $userId): ?KeyActivate
    {
        return KeyActivate::whereHas('keyActivateUser.serverUser', function ($query) use ($userId) {
            $query->where('id', $userId);
        })->first();
    }
}
