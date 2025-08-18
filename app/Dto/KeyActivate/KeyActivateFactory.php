<?php

namespace App\Dto\KeyActivate;

use App\Models\KeyActivate\KeyActivate;

class KeyActivateFactory
{
    /**
     * @param KeyActivate $key_activate
     * @return KeyActivateDto
     */
    public static function fromEntity(KeyActivate $key_activate): KeyActivateDto
    {
        $dto = new KeyActivateDto();
        $dto->id = $key_activate->id;
        $dto->traffic_limit = $key_activate->traffic_limit;
        $dto->pack_salesman_id = $key_activate->pack_salesman_id;
        $dto->finish_at = $key_activate->finish_at;
        $dto->user_tg_id = $key_activate->user_tg_id;
        $dto->deleted_at = $key_activate->deleted_at;
        $dto->status = $key_activate->status;

        return $dto;
    }
}
