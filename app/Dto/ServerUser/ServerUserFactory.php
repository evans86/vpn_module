<?php

namespace App\Dto\ServerUser;

use App\Models\ServerUser\ServerUser;

class ServerUserFactory
{
    /**
     * @param ServerUser $serverUser
     * @return ServerUserDto
     */
    public static function fromEntity(ServerUser $serverUser): ServerUserDto
    {
        $dto = new ServerUserDto();
        $dto->id = $serverUser->id;
        $dto->server_id = $serverUser->server_id;
        $dto->user_id = $serverUser->user_id;
        $dto->is_free = $serverUser->is_free;

        return $dto;
    }
}
