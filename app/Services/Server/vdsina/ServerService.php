<?php

namespace App\Services\Server\vdsina;

use App\Dto\Server\ServerDto;
use App\Dto\Server\ServerFactory;
use App\Models\Server\Server;
use App\Repositories\Server\ServerRepository;
use App\Services\Cloudflare\CloudflareService;
use App\Services\External\VdsinaAPI;

class ServerService
{
    //TODO: work method
    public function serverAPI()
    {
        $api_key_ru = '81470cecb8eb6f8da025c94b60aaba6e0563fb75794844b429d85cbf23800d4f';
        $api_key_com = '23c6ea5bd2a5ff8e009bbfa6fe0caf03bf80a7fd0dd1057eb9b04b09c1393575'; //пернести в .env

        $vdsinaApi = new VdsinaAPI($api_key_com);

        return $vdsinaApi->getVnsServer();
    }




    //Метод создания сервера
    //Вернёт созданный сервер с минимально возможными данными
    /**
     * @return ServerDto
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function create(): ServerDto
    {
        $vdsinaApi = new VdsinaAPI(config('services.api_keys.vdsina_key'));

        $new_password = bin2hex(openssl_random_pseudo_bytes(8));

        $server = $vdsinaApi->createServer('vpnserver' . time() . 'nz', 1); //вернет объект с id сервера
        //в запись добавить provider_id
        $provider_id = $server['data']['id'];

        $server = new ServerDto();

        $server->provider_id = $provider_id;
        $server->location_id = 1;
        $server->provider = Server::VDSINA;
        $server->server_status = Server::SERVER_CREATED;
        $server->is_free = true;

        Server::create($server->createArray());

        return $server;
    }

    //крон по обновлению данных всех
    public function cronStatus()
    {
        $servers = Server::query()->where('server_status', Server::SERVER_CREATED)->get();
        echo 'START check status for ' . count($servers) . ' servers' . PHP_EOL;

        foreach ($servers as $server) {
            $status = self::serverStatus($server->provider_id);
            if ($status) {
                self::update($server->provider_id);
                echo 'Server id=' . $server->provider_id . ' data update' . PHP_EOL;
            } else {
                echo 'Server id=' . $server->provider_id . ' status new' . PHP_EOL;
            }
        }

        echo 'FINISH check status ' . count($servers) . ' servers' . PHP_EOL;
    }

    // проверка инициализации сервера у провайдера
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

    // обновление данных с ресурса vdsina
    public function update(int $provider_id)
    {
        $vdsinaApi = new VdsinaAPI(config('services.api_keys.vdsina_key'));
        $vdsina_server = $vdsinaApi->getServerById($provider_id);

        $new_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 16);
        $password = $vdsinaApi->updatePassword($provider_id, $new_password);

        //вынести в конструктор
        $repository = new ServerRepository();
        $server = $repository->getByProviderId($provider_id);
        $ip = $vdsina_server['data']['ip'][0]['ip'];
        $name = $vdsina_server['data']['name'];

        //создание субдомена
        $cloudflare_service = new CloudflareService();
        $host = $cloudflare_service->createSubdomain($name, $ip);

        //обновление настроек сервера в БД
        $server->ip = $ip;
        $server->login = 'root';
        $server->password = $new_password;
        $server->name = $name;
        $server->host = $host;
        $server->server_status = Server::SERVER_CONFIGURED;

        $server->save();
    }
}
