<?php

namespace App\Dto\PackSalesman;

class PackSalesmanDto
{
    public int $id;
    public int $pack_id;
    public int $salesman_id;
    public int $status;

    public function getArray(): array
    {
        return [
            'id' => $this->id,
            'pack_id' => $this->pack_id,
            'salesman_id' => $this->salesman_id,
            'status' => $this->status,
        ];
    }
}
