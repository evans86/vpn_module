<?php

namespace App\Dto\KeyActivateUser;

use App\Models\KeyActivateUser\KeyActivateUser;

class KeyActivateUserFactory
{
    /**
     * @param KeyActivateUser $key_activate_user
     * @return KeyActivateUserDto
     */
    public static function fromEntity(KeyActivateUser $key_activate_user): KeyActivateUserDto
    {
        $dto = new KeyActivateUserDto();
        $dto->id = $key_activate_user->id;
        $dto->server_user_id = $key_activate_user->server_user_id;
        $dto->key_activate_id = $key_activate_user->key_activate_id;
        $dto->location_id = $key_activate_user->location_id;

        return $dto;
    }
}
