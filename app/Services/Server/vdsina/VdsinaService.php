<?php

namespace App\Services\Server\vdsina;

use App\Models\Location\Location;
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
     */
    public function configure(int $location_id, string $provider, bool $isFree): Server
    {
        /**
         * @var Location $location
         */
        $location = Location::query()->where('id', $location_id)->first();
        if (!$location) {
            throw new \RuntimeException('Location not found');
        }

        $vdsinaApi = new VdsinaAPI(config('services.api_keys.vdsina_key'));

        // Создаем сервер через API VDSina
        $serverResponse = $vdsinaApi->createServer('vpnserver' . time() . $location->code, 1);
        if (!isset($serverResponse['data']['id'])) {
            throw new \RuntimeException('Failed to create server in VDSina');
        }

        // Создаем запись о сервере
        $server = new Server();
        $server->provider_id = $serverResponse['data']['id'];
        $server->location_id = $location->id;
        $server->provider = $provider;
        $server->server_status = Server::SERVER_CREATED;
        $server->is_free = $isFree;
        $server->save();

        return $server;
    }

    /**
     * Окончательная настройка сервера
     *
     * @param int $server_id
     * @return void
     * @throws GuzzleException
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
     * @param $server_id
     * @return void
     * @throws GuzzleException
     */
    public function delete($server_id): void
    {
        try {
            /**
             * @var Server $server
             */
            $server = Server::query()->where('id', $server_id)->first();
            if (!$server) {
                throw new \RuntimeException('Server not found');
            }

            // Удаляем DNS запись
            if ($server->dns_record_id) {
                $cloudflare_service = new CloudflareService();
                $cloudflare_service->deleteSubdomain($server->dns_record_id);
            }

            // Удаляем сервер у провайдера
            $vdsinaApi = new VdsinaAPI(config('services.api_keys.vdsina_key'));
            $deleteData = $vdsinaApi->deleteServer($server->provider_id);

            if ($deleteData['status'] !== 'ok') {
                throw new \RuntimeException('Error deleting server in VDSina');
            }

            // Удаляем запись из базы
            if (!$server->delete()) {
                throw new \RuntimeException('Failed to delete server from database');
            }

            Log::info('Server deleted successfully', [
                'server_id' => $server_id,
                'provider_id' => $server->provider_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete server', [
                'server_id' => $server_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
