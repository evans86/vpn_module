<?php

namespace App\Dto\Pack;

use App\Models\Pack\Pack;

class PackFactory
{
    public static function fromEntity(Pack $pack): PackDto
    {
        $dto = new PackDto();
        $dto->id = $pack->id;
        $dto->price = $pack->price;
        $dto->period = $pack->period;
        $dto->traffic_limit = $pack->traffic_limit;
        $dto->count = $pack->count;
        $dto->activate_time = $pack->activate_time;
        $dto->status = $pack->status;

        return $dto;
    }
}
