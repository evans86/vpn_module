<?php

namespace App\Dto\KeyActivate;

use Faker\Provider\Uuid;

class KeyActivateDto
{
    public uuid $id;
    public int $traffic_limit;
    public int $pack_salesman_id;
    public int $finish_at;
    public ?int $user_tg_id;
    public int $deleted_at;
    public int $status;

    public function getArray(): array
    {
        return [
            'id' => $this->id,
            'traffic_limit' => $this->traffic_limit,
            'pack_salesman_id' => $this->pack_salesman_id,
            'finish_at' => $this->finish_at,
            'user_tg_id' => $this->user_tg_id,
            'deleted_at' => $this->deleted_at,
            'status' => $this->status,
        ];
    }
}