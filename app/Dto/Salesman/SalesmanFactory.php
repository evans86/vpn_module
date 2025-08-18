<?php

namespace App\Dto\Salesman;

use App\Models\Salesman\Salesman;

class SalesmanFactory
{
    /**
     * @param Salesman $salesman
     * @return SalesmanDto
     */
    public static function fromEntity(Salesman $salesman): SalesmanDto
    {
        $dto = new SalesmanDto();
        $dto->id = $salesman->id;
        $dto->telegram_id = $salesman->telegram_id;
        $dto->username = $salesman->username;
        $dto->token = $salesman->token;
        $dto->status = $salesman->status;
        $dto->bot_link = $salesman->bot_link;

        return $dto;
    }
}
