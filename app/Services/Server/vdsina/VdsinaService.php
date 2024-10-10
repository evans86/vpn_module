<?php

namespace App\Services\Server\vdsina;

use App\Models\Location\Location;
use App\Models\Server\Server;
use App\Services\Cloudflare\CloudflareService;
use App\Services\External\VdsinaAPI;
use GuzzleHttp\Exception\GuzzleException;

class VdsinaService
{
    /**
     * Первоначальная настройка сервера
     *
     * @param int $location_id
     * @param string $provider
     * @param bool $isFree
     * @return void
     * @throws GuzzleException
     */
    public function configure(int $location_id, string $provider, bool $isFree): void
    {
        /**
         * @var Location $location
         */
        $location = Location::query()->where('id', $location_id)->first();
        $vdsinaApi = new VdsinaAPI(config('services.api_keys.vdsina_key'));

        $server = $vdsinaApi->createServer('vpnserver' . time() . $location->code, 1); //вернет объект с id сервера
        //в запись добавить provider_id
        $provider_id = $server['data']['id'];

        $server = new Server();

        $server->provider_id = $provider_id;
        $server->location_id = $location->id;
        $server->provider = $provider;
        $server->server_status = Server::SERVER_CREATED;
        $server->is_free = $isFree;

        $server->save();
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
        /**
         * @var Server $server
         */
        $server = Server::query()->where('id', $server_id)->first();
        $vdsinaApi = new VdsinaAPI(config('services.api_keys.vdsina_key'));
        $vdsina_server = $vdsinaApi->getServerById($server->provider_id);
        $new_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 16);
        $vdsinaApi->updatePassword($server->provider_id, $new_password);

        //создание субдомена
        $cloudflare_service = new CloudflareService();
        $host = $cloudflare_service->createSubdomain($vdsina_server['data']['name'], $vdsina_server['data']['ip'][0]['ip']);

        $server->ip = $vdsina_server['data']['ip'][0]['ip'];
        $server->login = 'root';
        $server->password = $new_password;
        $server->name = $vdsina_server['data']['name'];
        $server->host = $host->name;
        $server->dns_record_id = $host->id;
        $server->server_status = Server::SERVER_CONFIGURED;

        $server->save();
    }

    /**
     * Проверка всех, только созданных серверов VDSINA
     *
     * @return void
     * @throws GuzzleException
     */
    public function checkStatus()
    {
        /**
         * @var Server[] $servers
         */
        $servers = Server::query()
            ->where('provider', Server::VDSINA)
            ->where('server_status', Server::SERVER_CREATED)->get();

        echo 'START check status for ' . count($servers) . ' VDSINA servers' . PHP_EOL;
        foreach ($servers as $server) {
            $status = self::serverStatus($server->provider_id);
            if ($status) {
                self::finishConfigure($server->id);
                echo 'Server id=' . $server->provider_id . ' data update' . PHP_EOL;
            } else {
                echo 'Server id=' . $server->provider_id . ' status new' . PHP_EOL;
            }
        }
        echo 'FINISH check status ' . count($servers) . ' VDSINA servers' . PHP_EOL;
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
        $vdsinaApi = new VdsinaAPI(config('services.api_keys.vdsina_key'));
        $server = $vdsinaApi->getServerById($provider_id);

        $external_status = $server['data']['status'];

        switch ($external_status) {
            case 'new':
                return false;
                break;
            case 'active':
                return true;
                break;
            default:
                throw new \DomainException('Undefind status ' . __FUNCTION__);
        }
    }

    public function delete($server_id)
    {
        $server = Server::query()->where('id', $server_id)->first();
        $cloudflare_service = new CloudflareService();

        //удаление субдомена
        $cloudflare_service->deleteSubdomain($server->dns_record_id);
    }
}
