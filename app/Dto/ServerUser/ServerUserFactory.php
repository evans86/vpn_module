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
        $dto->panel_id = $serverUser->panel_id;
        $dto->keys = $serverUser->keys;
        $dto->is_free = $serverUser->is_free;

        return $dto;
    }
}
