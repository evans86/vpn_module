<?php

namespace App\Dto\Location;

use App\Models\Location\Location;

class LocationFactory
{
    /**
     * @param Location $location
     * @return LocationDto
     */
    public static function fromEntity(Location $location): LocationDto
    {
        $dto = new LocationDto();
        $dto->id = $location->id;
        $dto->code = $location->code;
        $dto->emoji = $location->emoji;

        return $dto;
    }
}
