<?php

namespace App\Dto\Location;

class LocationDto
{
    public int $id;
    public string $code; // Код
    public string $emoji; // смайл

    public function getArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'emoji' => $this->emoji,
        ];
    }
}
