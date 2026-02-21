<?php

namespace App\Services\Server\timeweb;

use App\Models\Location\Location;
use App\Models\Panel\Panel;
use App\Models\Server\Server;
use App\Services\Cloudflare\CloudflareService;
use App\Services\External\TimewebCloudAPI;
use DomainException;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Сервис для работы с серверами Timeweb Cloud
 */
class TimewebService
{
    private TimewebCloudAPI $timewebApi;

    public function __construct()
    {
        $this->timewebApi = new TimewebCloudAPI(config('services.api_keys.timeweb_key'));
    }

    /**
     * Первоначальная настройка сервера
     */
    public function configure(int $location_id, string $provider, bool $isFree): Server
    {
        try {
            /**
             * @var Location $location
             */
            $location = Location::query()->where('id', $location_id)->first();
            if (!$location) {
                throw new RuntimeException('Location not found');
            }

            // 1. Маппинг локаций БД на Timeweb: location_id => [location code, availability_zone]
            // Амстердам (Нидерланды) = nl-1, ams-1
            $locationMapping = [
                Location::NL => ['id' => 'nl-1', 'availability_zone' => 'ams-1'], // Амстердам
                Location::RU => ['id' => 'ru-1', 'availability_zone' => 'spb-1'],
            ];
            $timewebLocation = $locationMapping[$location_id] ?? $locationMapping[Location::NL];
            Log::info('Timeweb location mapping', [
                'location_id' => $location_id,
                'timeweb' => $timewebLocation,
                'source' => 'server'
            ]);

            // 2. ОС: Ubuntu 24.04 (id 99) или 22.04 — из GET /api/v1/os/servers (servers_os)
            $os = null;
            try {
                $operatingSystems = $this->timewebApi->getOperatingSystems();
                $osList = $operatingSystems['servers_os'] ?? [];
                foreach ($osList as $item) {
                    $name = strtolower($item['name'] ?? '');
                    $version = $item['version'] ?? '';
                    if ($name !== 'ubuntu') {
                        continue;
                    }
                    if ($version === '24.04') {
                        $os = (int)($item['id'] ?? 0);
                        Log::info('Using Ubuntu 24.04', ['os_id' => $os, 'source' => 'server']);
                        break;
                    }
                    if ($version === '22.04' && $os === null) {
                        $os = (int)($item['id'] ?? 0);
                    }
                }
                if ($os === null && !empty($osList)) {
                    foreach ($osList as $item) {
                        if (strtolower($item['name'] ?? '') === 'ubuntu') {
                            $os = (int)($item['id'] ?? 0);
                            Log::info('Using Ubuntu fallback', ['os_id' => $os, 'version' => $item['version'] ?? '', 'source' => 'server']);
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get OS list', ['error' => $e->getMessage(), 'source' => 'server']);
            }
            if (!$os) {
                $os = 99; // известный os_id Ubuntu 24.04 по ответам API
                Log::info('Using default os_id 99 (Ubuntu 24.04)', ['source' => 'server']);
            }

            // 3. Конфигурация: 2×3.3 ГГц, 2 ГБ, 40 ГБ NVMe, 1 Гбит/с — по тарифу (preset) или configurator для nl-1
            $requiredCpu = 2;
            $requiredRam = 2048;
            $requiredDisk = 40960;
            $targetLocation = $timewebLocation['id']; // nl-1 или ru-1

            $presetId = null;
            $configuratorId = null;

            try {
                $presets = $this->timewebApi->getPresets();
                $presetList = $presets['server_presets'] ?? [];
                foreach ($presetList as $p) {
                    $loc = $p['location'] ?? '';
                    if ($loc !== $targetLocation) {
                        continue;
                    }
                    $cpu = (int)($p['cpu'] ?? 0);
                    $ram = (int)($p['ram'] ?? 0);
                    $disk = (int)($p['disk'] ?? 0);
                    if ($cpu >= $requiredCpu && $ram >= $requiredRam && $disk >= $requiredDisk) {
                        $presetId = (int)($p['id'] ?? 0);
                        Log::info('Selected preset for server', [
                            'preset_id' => $presetId,
                            'location' => $loc,
                            'cpu' => $cpu,
                            'ram' => $ram,
                            'disk' => $disk,
                            'source' => 'server'
                        ]);
                        break;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get presets', ['error' => $e->getMessage(), 'source' => 'server']);
            }

            if (!$presetId) {
                try {
                    $configurations = $this->timewebApi->getConfigurations();
                    $configList = $configurations['server_configurators'] ?? [];
                    foreach ($configList as $c) {
                        $loc = $c['location'] ?? '';
                        if ($loc !== $targetLocation) {
                            continue;
                        }
                        $req = $c['requirements'] ?? [];
                        $cpuMin = (int)($req['cpu_min'] ?? 0);
                        $ramMin = (int)($req['ram_min'] ?? 0);
                        $diskMin = (int)($req['disk_min'] ?? 0);
                        $diskType = strtolower($c['disk_type'] ?? '');
                        if ($cpuMin <= $requiredCpu && $ramMin <= $requiredRam && $diskMin <= $requiredDisk
                            && strpos($diskType, 'nvme') !== false) {
                            $configuratorId = (int)($c['id'] ?? 0);
                            Log::info('Selected configurator for server', [
                                'configurator_id' => $configuratorId,
                                'location' => $loc,
                                'source' => 'server'
                            ]);
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to get configurators', ['error' => $e->getMessage(), 'source' => 'server']);
                }
            }

            if (!$presetId && !$configuratorId) {
                throw new RuntimeException('No preset or configurator found for location ' . $targetLocation . ' with 2 CPU, 2 GB RAM, 40 GB. Check Timeweb Cloud availability.');
            }

            // Имя сервера в формате проекта: vpnserver{timestamp}-{locationCode}
            $locationCode = $location && is_object($location) ? ($location->code ?? 'nl') : 'nl';
            $serverName = 'vpnserver' . time() . '-' . strtolower($locationCode);

            $additionalParams = [
                'availability_zone' => $timewebLocation['availability_zone'],
                'is_ddos_guard'     => false,
            ];
            $projectId = config('services.timeweb.project_id');
            if ($projectId !== null && $projectId !== '') {
                $additionalParams['project_id'] = (int)$projectId;
            }
            if ($presetId) {
                $additionalParams['preset_id'] = $presetId;
            } else {
                $additionalParams['configurator_id'] = $configuratorId;
                $additionalParams['cpu'] = $requiredCpu;
                $additionalParams['ram'] = $requiredRam;
                $additionalParams['disk'] = $requiredDisk;
            }

            $serverResponse = $this->timewebApi->createServer(
                $serverName,
                (string)$os,
                $configuratorId ? (string)$configuratorId : '0',
                $timewebLocation['id'],
                $additionalParams
            );

            // Проверяем различные форматы ответа
            $serverId = null;
            if (isset($serverResponse['server']['id'])) {
                $serverId = $serverResponse['server']['id'];
            } elseif (isset($serverResponse['data']['id'])) {
                $serverId = $serverResponse['data']['id'];
            } elseif (isset($serverResponse['id'])) {
                $serverId = $serverResponse['id'];
            }

            if (!$serverId) {
                $errorMessage = 'Unknown error';
                if (isset($serverResponse['error']['message'])) {
                    $errorMessage = $serverResponse['error']['message'];
                } elseif (isset($serverResponse['message'])) {
                    $errorMessage = $serverResponse['message'];
                }
                throw new RuntimeException('Failed to create server in Timeweb Cloud: ' . $errorMessage);
            }

            // 5. Создаем запись о сервере
            $server = new Server();
            $server->provider_id = (string)$serverId;
            $server->location_id = $location->id;
            $server->provider = $provider;
            $server->server_status = Server::SERVER_CREATED;
            $server->is_free = $isFree;
            $server->save();

            return $server;

        } catch (Exception $e) {
            Log::critical('Failed to configure server - critical infrastructure failure', [
                'server_id' => $server->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'server'
            ]);
            throw $e;
        }
    }

    /**
     * Окончательная настройка сервера
     */
    public function finishConfigure(int $server_id)
    {
        try {
            Log::info('Starting server configuration', ['server_id' => $server_id, 'source' => 'server']);

            $server = Server::query()->where('id', $server_id)->first();
            if (!$server) {
                throw new RuntimeException('Server not found');
            }

            if (!$server->provider_id) {
                throw new RuntimeException('Server has no provider_id');
            }

            // Получаем информацию о сервере от провайдера
            $timeweb_server = $this->timewebApi->getServerById((int)$server->provider_id);

            // Проверяем различные форматы ответа
            $serverData = null;
            if (isset($timeweb_server['server'])) {
                $serverData = $timeweb_server['server'];
            } elseif (isset($timeweb_server['data'])) {
                $serverData = $timeweb_server['data'];
            } elseif (isset($timeweb_server['cloud_server'])) {
                $serverData = $timeweb_server['cloud_server'];
            } else {
                throw new RuntimeException('Invalid server response from Timeweb Cloud');
            }

            // Собираем IPv4 и IPv6 из networks[].ips[]; для DNS A-записи Cloudflare нужен только IPv4
            $serverIpV4 = null;
            $serverIpV6 = null;
            if (isset($serverData['networks']) && is_array($serverData['networks'])) {
                foreach ($serverData['networks'] as $net) {
                    $ips = $net['ips'] ?? [];
                    foreach ($ips as $ipItem) {
                        $type = strtolower($ipItem['type'] ?? '');
                        $ip = $ipItem['ip'] ?? $ipItem['address'] ?? null;
                        if (!$ip) {
                            continue;
                        }
                        if ($type === 'ipv4') {
                            $serverIpV4 = $ip;
                        } elseif ($type === 'ipv6') {
                            $serverIpV6 = $ip;
                        }
                    }
                }
            }
            if (!$serverIpV4 && isset($serverData['ip'])) {
                $flat = is_array($serverData['ip']) ? ($serverData['ip']['address'] ?? $serverData['ip'][0] ?? null) : $serverData['ip'];
                if ($flat && filter_var($flat, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $serverIpV4 = $flat;
                }
            }

            // Если у сервера только IPv6 — запрашиваем публичный IPv4 через API (платно, ~180 ₽/мес)
            if (!$serverIpV4 && $serverIpV6) {
                Log::info('Server has only IPv6, requesting public IPv4 via API', [
                    'server_id' => $server_id,
                    'provider_id' => $server->provider_id,
                    'source' => 'server'
                ]);
                try {
                    $this->timewebApi->addServerIp((int)$server->provider_id, 'ipv4');
                    sleep(5);
                    $timeweb_server = $this->timewebApi->getServerById((int)$server->provider_id);
                    $serverData = $timeweb_server['server'] ?? $timeweb_server['data'] ?? $timeweb_server['cloud_server'] ?? null;
                    if ($serverData && isset($serverData['networks']) && is_array($serverData['networks'])) {
                        foreach ($serverData['networks'] as $net) {
                            $ips = $net['ips'] ?? [];
                            foreach ($ips as $ipItem) {
                                $t = strtolower($ipItem['type'] ?? '');
                                $ip = $ipItem['ip'] ?? $ipItem['address'] ?? null;
                                if ($ip && $t === 'ipv4') {
                                    $serverIpV4 = $ip;
                                    break 2;
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Could not add IPv4 to server (balance or limit)', [
                        'server_id' => $server_id,
                        'error' => $e->getMessage(),
                        'source' => 'server'
                    ]);
                }
            }

            // Для DNS A-записи и сохранения в БД используем IPv4; без него Cloudflare вернёт ошибку
            $serverIp = $serverIpV4;
            if (!$serverIp) {
                throw new RuntimeException(
                    'Server has no IPv4 address (only IPv6: ' . ($serverIpV6 ?? 'none') . '). ' .
                    'Add a public IPv4 in Timeweb panel (Servers → server → IPs) or ensure balance allows automatic IPv4 add (~180 ₽/month).'
                );
            }

            // Пароль: root_pass в ответе сервера или отдельный запрос
            $realPassword = $serverData['root_pass'] ?? $serverData['password'] ?? null;
            if (!$realPassword) {
                $realPassword = $this->getServerPassword($server_id);
            }
            if (!$realPassword) {
                Log::warning('Could not retrieve password from Timeweb Cloud, using temporary value', ['source' => 'server']);
                $realPassword = 'TEMPORARY_PASSWORD_' . time();
            }

            // Создаем или обновляем DNS запись
            $serverName = $serverData['name'] ?? 'server-' . $server->provider_id;

            Log::info('Creating/updating DNS record', [
                'server_id' => $server_id,
                'name' => $serverName,
                'ip' => $serverIp,
                'source' => 'server'
            ]);

            $cloudflare_service = new CloudflareService();
            $host = $cloudflare_service->createSubdomain($serverName, $serverIp);

            if (!isset($host->id) || !isset($host->name)) {
                throw new RuntimeException('Invalid response from Cloudflare: missing id or name');
            }

            // Ждем 5 секунд для пропагации DNS
            sleep(5);

            // Сохраняем РЕАЛЬНЫЙ пароль (или временный если не удалось получить)
            $server->ip = $serverIp;
            $server->login = 'root';
            $server->password = $realPassword;
            $server->name = $serverName;
            $server->host = $host->name;
            $server->dns_record_id = $host->id;
            $server->server_status = Server::SERVER_CONFIGURED;
            $server->save();

            Log::info('Server configuration completed with password', [
                'server_id' => $server_id,
                'provider_id' => $server->provider_id,
                'source' => 'server',
                'password_set' => $realPassword !== 'TEMPORARY_PASSWORD_' . time()
            ]);

        } catch (Exception $e) {
            Log::critical('Failed to configure server - critical infrastructure failure', [
                'server_id' => $server_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'server'
            ]);

            if (isset($server)) {
                $server->server_status = Server::SERVER_ERROR;
                $server->save();
            }

            throw $e;
        }
    }

    /**
     * Получить реальный пароль сервера от Timeweb Cloud
     */
    public function getServerPassword(int $server_id): ?string
    {
        try {
            $server = Server::query()->where('id', $server_id)->first();
            if (!$server || !$server->provider_id) {
                Log::error('Server not found or no provider_id', ['server_id' => $server_id, 'source' => 'server']);
                return null;
            }

            Log::info('Getting server password from Timeweb Cloud API', [
                'server_id' => $server_id,
                'provider_id' => $server->provider_id,
                'source' => 'server'
            ]);

            // Используем специальный эндпоинт для получения пароля
            $passwordResponse = $this->timewebApi->getServerPassword((int)$server->provider_id);

            // Пароль может быть в разных местах ответа (API возвращает из getServerById)
            if (isset($passwordResponse['root_pass']) && $passwordResponse['root_pass'] !== '') {
                return $passwordResponse['root_pass'];
            }
            if (isset($passwordResponse['password']) && $passwordResponse['password'] !== '') {
                $password = $passwordResponse['password'];

                Log::info('Password retrieved successfully from Timeweb Cloud', [
                    'server_id' => $server_id,
                    'password_length' => strlen($password),
                    'source' => 'server'
                ]);

                return $password;
            }

            if (isset($passwordResponse['data']['password']) && !empty($passwordResponse['data']['password'])) {
                $password = $passwordResponse['data']['password'];

                Log::info('Password retrieved successfully from Timeweb Cloud (nested)', [
                    'server_id' => $server_id,
                    'password_length' => strlen($password),
                    'source' => 'server'
                ]);

                return $password;
            }

            Log::warning('No password found in Timeweb Cloud password response', [
                'server_id' => $server_id,
                'response_structure' => $passwordResponse,
                'source' => 'server'
            ]);

            return null;

        } catch (Exception $e) {
            Log::error('Failed to get server password from Timeweb Cloud', [
                'source' => 'server',
                'server_id' => $server_id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Проверка статуса конкретного сервера у провайдера
     * 
     * @param Server $server Сервер для проверки
     * @return bool true если сервер готов к настройке, false если еще не готов
     * @throws Exception При ошибках проверки
     */
    public function checkServerStatus(Server $server): bool
    {
        try {
            if (!$server->provider_id) {
                Log::error('Server has no provider_id', [
                    'server_id' => $server->id,
                    'source' => 'server'
                ]);
                return false;
            }

            Log::info('Checking server status', [
                'server_id' => $server->id,
                'provider_id' => $server->provider_id,
                'source' => 'server'
            ]);

            $status = $this->serverStatus((int)$server->provider_id);

            if ($status) {
                Log::info('Server is ready for configuration', [
                    'server_id' => $server->id,
                    'provider_id' => $server->provider_id,
                    'source' => 'server'
                ]);

                $this->finishConfigure($server->id);

                Log::info('Server configured successfully', [
                    'server_id' => $server->id,
                    'provider_id' => $server->provider_id,
                    'source' => 'server'
                ]);
                
                return true;
            } else {
                Log::info('Server not ready yet', [
                    'server_id' => $server->id,
                    'provider_id' => $server->provider_id,
                    'source' => 'server'
                ]);
                
                return false;
            }
        } catch (Exception $e) {
            Log::error('Error checking server status', [
                'server_id' => $server->id,
                'provider_id' => $server->provider_id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'server'
            ]);

            // Помечаем сервер как ошибочный
            $server->server_status = Server::SERVER_ERROR;
            $server->save();
            
            throw $e;
        }
    }

    /**
     * Проверка статуса создания сервера у провайдера
     */
    public function serverStatus(int $provider_id): bool
    {
        try {
            $server = $this->timewebApi->getServerById($provider_id);

            // Проверяем различные форматы ответа
            $serverInfo = null;
            if (isset($server['server'])) {
                $serverInfo = $server['server'];
            } elseif (isset($server['data'])) {
                $serverInfo = $server['data'];
            } elseif (isset($server['cloud_server'])) {
                $serverInfo = $server['cloud_server'];
            } else {
                $serverInfo = $server; // Если данные уже на верхнем уровне
            }

            if (!isset($serverInfo['status'])) {
                Log::error('Invalid server response from Timeweb Cloud', [
                    'provider_id' => $provider_id,
                    'response' => $server,
                    'source' => 'server'
                ]);
                throw new RuntimeException('Invalid server response: status not found');
            }

            $external_status = $serverInfo['status'];
            Log::info('Server status received from Timeweb Cloud', [
                'provider_id' => $provider_id,
                'status' => $external_status,
                'source' => 'server'
            ]);

            // Статусы Timeweb Cloud: 'on' = запущен, 'creating'/'installing' = ещё создаётся
            switch (strtolower($external_status)) {
                case 'creating':
                case 'installing':
                    return false;
                case 'on':
                case 'active':
                case 'running':
                    return true;
                case 'error':
                case 'failed':
                    throw new DomainException('Server creation failed with status: ' . $external_status);
                default:
                    Log::warning('Unknown server status from Timeweb Cloud', [
                        'provider_id' => $provider_id,
                        'status' => $external_status,
                        'source' => 'server'
                    ]);
                    // Если статус неизвестен, считаем что сервер еще не готов
                    return false;
            }
        } catch (Exception $e) {
            Log::error('Error checking server status in Timeweb Cloud', [
                'source' => 'server',
                'provider_id' => $provider_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Удаление сервера
     */
    public function delete(Server $server): void
    {
        try {
            Log::info('Starting server deletion', [
                'server_id' => $server->id,
                'provider_id' => $server->provider_id,
                'source' => 'server'
            ]);

            // Удаляем сервер у провайдера
            if ($server->provider_id) {
                try {
                    $this->timewebApi->deleteServer((int)$server->provider_id);
                    Log::info('Server deleted from provider', [
                        'source' => 'server',
                        'server_id' => $server->id,
                        'provider_id' => $server->provider_id
                    ]);
                } catch (Exception $e) {
                    Log::warning('Failed to delete server from provider, continuing with local cleanup', [
                        'source' => 'server',
                        'server_id' => $server->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Удаляем DNS-запись из Cloudflare
            if ($server->dns_record_id) {
                try {
                    $cloudflare = new CloudflareService();
                    $cloudflare->deleteSubdomain($server->dns_record_id);
                    Log::info('DNS record deleted from Cloudflare', [
                        'source' => 'server',
                        'server_id' => $server->id,
                        'dns_record_id' => $server->dns_record_id
                    ]);
                } catch (Exception $e) {
                    Log::warning('Failed to delete DNS record from Cloudflare', [
                        'source' => 'server',
                        'server_id' => $server->id,
                        'dns_record_id' => $server->dns_record_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Удаляем связанные панели
            if ($server->panel) {
                $server->panel->panel_status = Panel::PANEL_DELETED;
                $server->panel->save();
            }

            // Удаляем сервер из базы
            $server->server_status = Server::SERVER_DELETED;
            $server->save();

            Log::info('Server and related records deleted successfully', [
                'source' => 'server',
                'server_id' => $server->id
            ]);

        } catch (Exception $e) {
            Log::error('Error deleting server', [
                'source' => 'server',
                'server_id' => $server->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Проверка доступности сервера
     */
    public function ping(Server $server): bool
    {
        try {
            if (!$server->provider_id) {
                return false;
            }

            $serverData = $this->timewebApi->getServerById((int)$server->provider_id);
            
            if (!isset($serverData['server'])) {
                return false;
            }

            $status = strtolower($serverData['server']['status'] ?? '');
            return in_array($status, ['active', 'running']);

        } catch (Exception $e) {
            Log::error('Error pinging server', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
                'source' => 'server'
            ]);
            return false;
        }
    }
}

