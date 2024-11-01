<?php

namespace App\Dto\KeyActivateUser;

class KeyActivateUserDto
{
    public int $id;
    public string $server_user_id;
    public string $key_activate_id;
    public int $location_id;

    public function getArray(): array
    {
        return [
            'id' => $this->id,
            'server_user_id' => $this->server_user_id,
            'key_activate_id' => $this->key_activate_id,
            'location_id' => $this->location_id
        ];
    }
}
