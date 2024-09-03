<?php

namespace App\Dto\Server;

use App\Models\Server\Server;

class ServerFactory
{
    /**
     * @param Server $server
     * @return ServerDto
     */
    public static function fromEntity(Server $server): ServerDto
    {
        $dto = new ServerDto();
        $dto->id = $server->id;
        $dto->provider_id = $server->provider_id;
        $dto->ip = $server->ip;
        $dto->login = $server->login;
        $dto->password = $server->password;
        $dto->name = $server->name;
        $dto->host = $server->host;
        $dto->provider = $server->provider;
        $dto->panel = $server->panel;
        $dto->location_id = $server->location_id;
        $dto->panel_adress = $server->panel_adress;
        $dto->panel_login = $server->panel_login;
        $dto->panel_password = $server->panel_password;
        $dto->panel_key = $server->panel_key;
        $dto->is_free = $server->is_free;

        return $dto;
    }
}
