<?php

namespace App\Services\Server\vdsina;

use App\Models\Location\Location;
use App\Models\Panel\Panel;
use App\Models\Server\Server;
use App\Services\Cloudflare\CloudflareService;
use App\Services\External\VdsinaAPI;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class VdsinaService
{
    /**
     * Первоначальная настройка сервера
     *
     * @param int $location_id
     * @param string $provider
     * @param bool $isFree
     * @return Server
     * @throws GuzzleException
     * @throws \Exception
     */
    public function configure(int $location_id, string $provider, bool $isFree): Server
    {
        try {
            /**
             * @var Location $location
             */
            $location = Location::query()->where('id', $location_id)->first();
            if (!$location) {
                throw new \RuntimeException('Location not found');
            }

            $vdsinaApi = new VdsinaAPI(config('services.api_keys.vdsina_key'));

            // 1. Проверяем доступность дата-центров
            $datacenters = $vdsinaApi->getDatacenter();
            if (!isset($datacenters['data']) || !is_array($datacenters['data'])) {
                throw new \RuntimeException('Invalid datacenter response from VDSina');
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
                throw new \RuntimeException('Amsterdam datacenter not found in VDSina');
            }

            // 2. Проверяем доступность шаблонов ОС
            $templates = $vdsinaApi->getTemplate();
            if (!isset($templates['data']) || !is_array($templates['data'])) {
                throw new \RuntimeException('Invalid template response from VDSina');
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
                throw new \RuntimeException('Ubuntu 24.04 template not found in VDSina');
            }

            // 3. Проверяем доступность тарифных планов
            $plans = $vdsinaApi->getServerPlan();
            if (!isset($plans['data']) || !is_array($plans['data'])) {
                throw new \RuntimeException('Invalid server plan response from VDSina');
            }

            // Ищем базовый тарифный план (id = 1)
            $serverPlan = null;
            foreach ($plans['data'] as $plan) {
                if (isset($plan['id']) && $plan['id'] == 1) {
                    $serverPlan = $plan;
                    break;
                }
            }

            if (!$serverPlan) {
                throw new \RuntimeException('Basic server plan not found in VDSina');
            }

            // 4. Создаем сервер через API VDSina
            $serverName = 'vpnserver' . time() . $location->code;
            $serverResponse = $vdsinaApi->createServer(
                $serverName,
                $serverPlan['id'],
                0, // autoprolong
                $datacenter['id'],
                $template['id']
            );

            if (!isset($serverResponse['data']['id']) || !isset($serverResponse['status']) || $serverResponse['status'] !== 'ok') {
                throw new \RuntimeException('Failed to create server in VDSina: ' . ($serverResponse['status_msg'] ?? 'Unknown error'));
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

        } catch (\Exception $e) {
            Log::error('Failed to configure server', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Окончательная настройка сервера
     *
     * @param int $server_id
     * @return void
     * @throws GuzzleException
     * @throws \Exception
     */
    public function finishConfigure(int $server_id)
    {
        try {
            Log::info('Starting server configuration', ['server_id' => $server_id]);

            /**
             * @var Server $server
             */
            $server = Server::query()->where('id', $server_id)->first();
            if (!$server) {
                throw new RuntimeException('Server not found');
            }

            if (!$server->provider_id) {
                throw new RuntimeException('Server has no provider_id');
            }

            // Получаем информацию о сервере от провайдера
            $vdsinaApi = new VdsinaAPI(config('services.api_keys.vdsina_key'));
            $vdsina_server = $vdsinaApi->getServerById($server->provider_id);

            if (!isset($vdsina_server['data']['ip'][0]['ip'])) {
                throw new RuntimeException('Server IP not found in provider response');
            }

            // Генерируем и обновляем пароль
            $new_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 16);
            Log::info('Updating server password', [
                'server_id' => $server_id,
                'provider_id' => $server->provider_id
            ]);
            $vdsinaApi->updatePassword($server->provider_id, $new_password);

            // Создаем или обновляем DNS запись
            $serverName = $vdsina_server['data']['name'];
            $serverIp = $vdsina_server['data']['ip'][0]['ip'];

            Log::info('Creating/updating DNS record', [
                'server_id' => $server_id,
                'name' => $serverName,
                'ip' => $serverIp
            ]);

            try {
                $cloudflare_service = new CloudflareService();
                $host = $cloudflare_service->createSubdomain($serverName, $serverIp);

                if (!isset($host->id) || !isset($host->name)) {
                    throw new RuntimeException('Invalid response from Cloudflare: missing id or name');
                }

                // Обновляем информацию о сервере
                $server->ip = $serverIp;
                $server->login = 'root';
                $server->password = $new_password;
                $server->name = $serverName;
                $server->host = $host->name;
                $server->dns_record_id = $host->id;
                $server->server_status = Server::SERVER_CONFIGURED;

                $server->save();

                Log::info('Server configuration completed', [
                    'server_id' => $server_id,
                    'provider_id' => $server->provider_id,
                    'host' => $host->name,
                    'dns_record_id' => $host->id
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to configure DNS for server', [
                    'server_id' => $server_id,
                    'name' => $serverName,
                    'ip' => $serverIp,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                // Даже если не удалось настроить DNS, сохраняем основную информацию о сервере
                $server->ip = $serverIp;
                $server->login = 'root';
                $server->password = $new_password;
                $server->name = $serverName;
                $server->server_status = Server::SERVER_ERROR;
                $server->save();

                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Failed to configure server', [
                'server_id' => $server_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Помечаем сервер как ошибочный
            if (isset($server)) {
                $server->server_status = Server::SERVER_ERROR;
                $server->save();
            }

            throw $e;
        }
    }

    /**
     * Проверка всех, только созданных серверов VDSINA
     *
     * @return void
     * @throws GuzzleException
     * @throws \Exception
     */
    public function checkStatus()
    {
        try {
            /**
             * @var Server[] $servers
             */
            $servers = Server::query()
                ->where('provider', Server::VDSINA)
                ->where('server_status', Server::SERVER_CREATED)
                ->get();

            Log::info('Checking status for VDSINA servers', ['count' => count($servers)]);

            foreach ($servers as $server) {
                try {
                    if (!$server->provider_id) {
                        Log::error('Server has no provider_id', [
                            'server_id' => $server->id
                        ]);
                        continue;
                    }

                    Log::info('Checking server status', [
                        'server_id' => $server->id,
                        'provider_id' => $server->provider_id
                    ]);

                    $status = $this->serverStatus($server->provider_id);

                    if ($status) {
                        Log::info('Server is ready for configuration', [
                            'server_id' => $server->id,
                            'provider_id' => $server->provider_id
                        ]);

                        $this->finishConfigure($server->id);

                        Log::info('Server configured successfully', [
                            'server_id' => $server->id,
                            'provider_id' => $server->provider_id
                        ]);
                    } else {
                        Log::info('Server not ready yet', [
                            'server_id' => $server->id,
                            'provider_id' => $server->provider_id
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Error processing server', [
                        'server_id' => $server->id,
                        'provider_id' => $server->provider_id ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    // Помечаем сервер как ошибочный
                    $server->server_status = Server::SERVER_ERROR;
                    $server->save();
                }
            }

            Log::info('Finished checking VDSINA servers', ['count' => count($servers)]);

        } catch (\Exception $e) {
            Log::error('Error in checkStatus', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Проверка статуса создания сервера у провайдера
     *
     * @param int $provider_id
     * @return bool
     * @throws GuzzleException
     * @throws \Exception
     */
    public function serverStatus(int $provider_id): bool
    {
        try {
            Log::info('Checking server status in VDSina', ['provider_id' => $provider_id]);

            $vdsinaApi = new VdsinaAPI(config('services.api_keys.vdsina_key'));
            $server = $vdsinaApi->getServerById($provider_id);

            if (!isset($server['data']['status'])) {
                Log::error('Invalid server response from VDSina', [
                    'provider_id' => $provider_id,
                    'response' => $server
                ]);
                throw new \RuntimeException('Invalid server response: status not found');
            }

            $external_status = $server['data']['status'];
            Log::info('Server status received from VDSina', [
                'provider_id' => $provider_id,
                'status' => $external_status
            ]);

            switch ($external_status) {
                case 'new':
                    return false;
                case 'active':
                    return true;
                default:
                    Log::error('Undefined server status from VDSina', [
                        'provider_id' => $provider_id,
                        'status' => $external_status
                    ]);
                    throw new \DomainException('Undefined status: ' . $external_status);
            }
        } catch (\Exception $e) {
            Log::error('Error checking server status in VDSina', [
                'provider_id' => $provider_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Удаление сервера
     *
     * @param Server $server
     * @return void
     * @throws \Exception
     */
    public function delete(Server $server): void
    {
        try {
            $vdsinaApi = new VdsinaAPI(config('services.api_keys.vdsina_key'));

            // Удаляем сервер через API VDSina
            $response = $vdsinaApi->deleteServer($server->provider_id);

            if (!isset($response['status']) || $response['status'] !== 'ok') {
                throw new \RuntimeException(
                    'Failed to delete server in VDSina: ' .
                    ($response['description'] ?? $response['status_msg'] ?? 'Unknown error')
                );
            }

            // Удаляем DNS запись, если она существует
            if ($server->dns_record_id) {
                try {
                    $cloudflare = new CloudflareService();
                    $cloudflare->deleteSubdomain($server->dns_record_id);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete DNS record', [
                        'server_id' => $server->id,
                        'dns_record_id' => $server->dns_record_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Обновляем статус всех связанных панелей на "удалена"
            $server->panels()->update(['panel_status' => Panel::PANEL_DELETED]);

            // Обновляем статус сервера на "Удален"
            $server->server_status = Server::SERVER_DELETED;
            $server->save();

            Log::info('Successfully deleted server in VDSina', [
                'server_id' => $server->id,
                'provider_id' => $server->provider_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete server in VDSina', [
                'server_id' => $server->id,
                'provider_id' => $server->provider_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
