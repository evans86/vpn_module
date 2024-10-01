<?php

namespace App\Dto\ServerUser;

use Faker\Provider\Uuid;

class ServerUserDto
{
    public uuid $id;
    public int $panel_id; // какой панели принадлежит пользователь
    public string $user_id; // из панели
    public bool $is_free;

    public function getArray(): array
    {
        return [
            'id' => $this->id,
            'panel_id' => $this->panel_id,
            'user_id' => $this->user_id,
            'is_free' => $this->is_free
        ];
    }
}
