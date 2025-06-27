<?php

namespace App\Dto\Pack;

class PackDto
{
    public int $id;
    public ?string $title;
    public ?int $module_key;
    public int $price;
    public int $period; //30 дней
    public int $traffic_limit;
    public int $count;
    public ?int $activate_time; //240 часов
    public bool $status;

    public function getArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'price' => $this->price,
            'period' => $this->period,
            'traffic_limit' => $this->traffic_limit,
            'count' => $this->count,
            'activate_time' => $this->activate_time,
            'status' => $this->status,
            'module_key' => $this->module_key,
        ];
    }
}
