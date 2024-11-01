<?php

namespace App\Dto\Pack;

class PackDto
{
    public int $id;
    public int $price;
    public int $period;
    public int $traffic_limit;
    public int $count;
    public int $activate_time;
    public bool $status;

    public function getArray(): array
    {
        return [
            'id' => $this->id,
            'price' => $this->price,
            'period' => $this->period,
            'traffic_limit' => $this->traffic_limit,
            'count' => $this->count,
            'activate_time' => $this->activate_time,
            'status' => $this->status,
        ];
    }
}
