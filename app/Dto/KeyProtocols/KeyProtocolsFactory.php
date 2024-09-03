<?php

namespace App\Dto\KeyProtocols;

use App\Models\KeyProtocols\KeyProtocols;

class KeyProtocolsFactory
{
    /**
     * @param KeyProtocols $keyProtocols
     * @return KeyProtocolsDto
     */
    public static function fromEntity(KeyProtocols $keyProtocols): KeyProtocolsDto
    {
        $dto = new KeyProtocolsDto();
        $dto->id = $keyProtocols->id;
        $dto->user_id = $keyProtocols->user_id;
        $dto->key = $keyProtocols->key;

        return $dto;
    }
}
