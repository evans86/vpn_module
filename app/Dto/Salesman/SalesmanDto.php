<?php

namespace App\Dto\Salesman;

class SalesmanDto
{
    public int $id;
    public int $telegram_id;
    public string $username;
    public ?string $token;
    public bool $status;
    public ?string $bot_link;

    public function getArray(): array
    {
        return [
            'id' => $this->id,
            'telegram_id' => $this->telegram_id,
            'username' => $this->username,
            'token' => $this->token,
            'status' => $this->status,
            'bot_link' => $this->bot_link,
        ];
    }
}
