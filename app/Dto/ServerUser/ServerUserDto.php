<?php

namespace App\Dto\ServerUser;

class ServerUserDto
{
    public string $id;
    public int $panel_id; // какой панели принадлежит пользователь
    public string $keys;
    public bool $is_free;

    public function getArray(): array
    {
        return [
            'id' => $this->id,
            'panel_id' => $this->panel_id,
            'keys' => $this->keys,
            'is_free' => $this->is_free
        ];
    }
}
