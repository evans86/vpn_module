<?php

namespace App\Services\Server\vdsina;

use App\Models\Location\Location;
use App\Models\Panel\Panel;
use App\Models\Server\Server;
use App\Services\Cloudflare\CloudflareService;
use App\Services\External\VdsinaAPI;
use DomainException;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class VdsinaService
{
    private VdsinaAPI $vdsinaApi;

    public function __construct()
    {
        $this->vdsinaApi = new VdsinaAPI(config('services.api_keys.vdsina_key'));
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

            // 1. Проверяем доступность дата-центров
            $datacenters = $this->vdsinaApi->getDatacenter();
            if (!isset($datacenters['data']) || !is_array($datacenters['data'])) {
                throw new RuntimeException('Invalid datacenter response from VDSina');
            }

            // Ищем датацентр Amsterdam (id = 1)
            $datacenter = null;
            foreach ($datacenters['data'] as $dc) {
                if (isset($dc['id']) && $dc['id'] == 1) {
                    $datacenter = $dc;
                    break;
                }
            }

            if (!$datacenter) {
                throw new RuntimeException('Amsterdam datacenter not found in VDSina');
            }

            // 2. Проверяем доступность шаблонов ОС
            $templates = $this->vdsinaApi->getTemplate();
            if (!isset($templates['data']) || !is_array($templates['data'])) {
                throw new RuntimeException('Invalid template response from VDSina');
            }

            // Ищем Ubuntu 24.04 (id = 23)
            $template = null;
            foreach ($templates['data'] as $tmpl) {
                if (isset($tmpl['id']) && $tmpl['id'] == 23) {
                    $template = $tmpl;
                    break;
                }
            }

            if (!$template) {
                throw new RuntimeException('Ubuntu 24.04 template not found in VDSina');
            }

            // 3. Проверяем доступность тарифных планов
            $plans = $this->vdsinaApi->getServerPlan(2); // ID группы серверов = 2
            if (!isset($plans['data']) || !is_array($plans['data'])) {
                throw new RuntimeException('Invalid server plan response from VDSina');
            }

            // Ищем базовый тарифный план (id = 3)
            $serverPlan = null;
            foreach ($plans['data'] as $plan) {
                if (isset($plan['id']) && $plan['id'] == 3) {
                    $serverPlan = $plan;
                    break;
                }
            }

            if (!$serverPlan) {
                throw new RuntimeException('Basic server plan not found in VDSina');
            }

            // 4. Создаем сервер через API VDSina
            $serverName = 'vpnserver' . time() . $location->code;
            $serverResponse = $this->vdsinaApi->createServer(
                $serverName,
                $serverPlan['id'],
                0, // autoprolong
                $datacenter['id'],
                $template['id']
            );

            if (!isset($serverResponse['data']['id']) || !isset($serverResponse['status']) || $serverResponse['status'] !== 'ok') {
                throw new RuntimeException('Failed to create server in VDSina: ' . ($serverResponse['status_msg'] ?? 'Unknown error'));
            }

            // 5. Создаем запись о сервере
            $server = new Server();
            $server->provider_id = $serverResponse['data']['id'];
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
            $vdsina_server = $this->vdsinaApi->getServerById($server->provider_id);

            if (!isset($vdsina_server['data']['ip']['ip'])) {
                throw new RuntimeException('Server IP not found in provider response');
            }

            // Удаляем бэкапы
            $this->vdsinaApi->deleteAllBackups($server->provider_id);

            // ПОЛУЧАЕМ РЕАЛЬНЫЙ ПАРОЛЬ СРАЗУ
            $realPassword = $this->getServerPassword($server_id);

            if (!$realPassword) {
                Log::warning('Could not retrieve password from VDSina, using temporary value', ['source' => 'server']);
                $realPassword = 'TEMPORARY_PASSWORD_' . time();
            }

            // Создаем или обновляем DNS запись
            $serverName = $vdsina_server['data']['name'];
            $serverIp = $vdsina_server['data']['ip']['ip'];

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
     * Получить реальный пароль сервера от VDSina
     */
    public function getServerPassword(int $server_id): ?string
    {
        try {
            $server = Server::query()->where('id', $server_id)->first();
            if (!$server || !$server->provider_id) {
                Log::error('Server not found or no provider_id', ['server_id' => $server_id, 'source' => 'server']);
                return null;
            }

            Log::info('Getting server password from VDSina API', [
                'server_id' => $server_id,
                'provider_id' => $server->provider_id,
                'source' => 'server'
            ]);

            // Используем специальный эндпоинт для получения пароля
            $passwordResponse = $this->vdsinaApi->getServerPassword($server->provider_id);

            // Согласно документации, пароль находится в data.password
            if (isset($passwordResponse['data']['password']) && !empty($passwordResponse['data']['password'])) {
                $password = $passwordResponse['data']['password'];

                Log::info('Password retrieved successfully from VDSina', [
                    'server_id' => $server_id,
                    'password_length' => strlen($password),
                    'source' => 'server'
                ]);

                return $password;
            }

            Log::warning('No password found in VDSina password response', [
                'server_id' => $server_id,
                'response_structure' => $passwordResponse,
                'source' => 'server'
            ]);

            return null;

        } catch (Exception $e) {
            Log::error('Failed to get server password from VDSina', [
                'source' => 'server',
                'server_id' => $server_id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Проверка всех, только созданных серверов VDSINA
     */
    public function checkStatus()
    {
        try {
            /**
             * @var Server[] $servers
             */
            // Оптимизация: добавляем eager loading для связанных данных
            $servers = Server::query()
                ->where('provider', Server::VDSINA)
                ->where('server_status', Server::SERVER_CREATED)
                ->with(['location', 'panel'])
                ->get();

            Log::info('Checking status for VDSINA servers', ['count' => count($servers), 'source' => 'server']);

            foreach ($servers as $server) {
                try {
                    if (!$server->provider_id) {
                        Log::error('Server has no provider_id', [
                            'server_id' => $server->id,
                            'source' => 'server'
                        ]);
                        continue;
                    }

                    Log::info('Checking server status', [
                        'server_id' => $server->id,
                        'provider_id' => $server->provider_id,
                        'source' => 'server'
                    ]);

                    $status = $this->serverStatus($server->provider_id);

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
                    } else {
                        Log::info('Server not ready yet', [
                            'server_id' => $server->id,
                            'provider_id' => $server->provider_id,
                            'source' => 'server'
                        ]);
                    }
                } catch (Exception $e) {
                    Log::error('Error processing server', [
                        'server_id' => $server->id,
                        'provider_id' => $server->provider_id ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'source' => 'server'
                    ]);

                    // Помечаем сервер как ошибочный
                    $server->server_status = Server::SERVER_ERROR;
                    $server->save();
                }
            }

        } catch (Exception $e) {
            Log::error('Error in checkStatus', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'server'
            ]);
            throw $e;
        }
    }

    /**
     * Проверка статуса создания сервера у провайдера
     */
    public function serverStatus(int $provider_id): bool
    {
        try {
            $server = $this->vdsinaApi->getServerById($provider_id);

            if (!isset($server['data']['status'])) {
                Log::error('Invalid server response from VDSina', [
                    'provider_id' => $provider_id,
                    'response' => $server,
                    'source' => 'server'
                ]);
                throw new RuntimeException('Invalid server response: status not found');
            }

            $external_status = $server['data']['status'];
            Log::info('Server status received from VDSina', [
                'provider_id' => $provider_id,
                'status' => $external_status,
                'source' => 'server'
            ]);

            switch ($external_status) {
                case 'new':
                    return false;
                case 'active':
                    return true;
                default:
                    Log::error('Undefined server status from VDSina', [
                        'provider_id' => $provider_id,
                        'status' => $external_status,
                        'source' => 'server'
                    ]);
                    throw new DomainException('Undefined status: ' . $external_status);
            }
        } catch (Exception $e) {
            Log::error('Error checking server status in VDSina', [
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
                    $this->vdsinaApi->deleteServer($server->provider_id);
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
}
