<?php

namespace App\Dto\PackSalesman;

use App\Models\PackSalesman\PackSalesman;

class PackSalesmanFactory
{
    /**
     * @param PackSalesman $pack_salesman
     * @return PackSalesmanDto
     */
    public static function fromEntity(PackSalesman $pack_salesman): PackSalesmanDto
    {
        $dto = new PackSalesmanDto();
        $dto->id = $pack_salesman->id;
        $dto->pack_id = $pack_salesman->pack_id;
        $dto->salesman_id = $pack_salesman->salesman_id;
        $dto->status = $pack_salesman->status;

        return $dto;
    }
}
