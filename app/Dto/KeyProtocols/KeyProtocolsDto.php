<?php

namespace App\Dto\KeyProtocols;

class KeyProtocolsDto
{
    public int $id;
    public int $user_id; //к какому пользователю относится
    public string $key; // ссылка подключения

    public function getArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'key' => $this->key,
        ];
    }
}
