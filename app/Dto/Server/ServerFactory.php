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
        $dto->location_id = $server->location_id;
        $dto->server_status = $server->server_status;
        $dto->is_free = $server->is_free;

        return $dto;
    }
}
