<?php

namespace App\Dto\Bot;

class BotModuleDto
{
    public int $id;
    public string $public_key;
    public string $private_key;
    public int $bot_id;
    public int $category_id;
    public int $version;
    public int $is_paid;
    public string $secret_user_key;
    public ?string $tariff_cost;
    public ?int $bot_user_id;

    public function getArray(): array
    {
        return [
            'id' => $this->id,
            'public_key' => $this->public_key,
            'private_key' => $this->private_key,
            'bot_id' => $this->bot_id,
            'category_id' => $this->category_id,
            'version' => $this->version,
            'secret_user_key' => $this->secret_user_key,
            'tariff_cost' => $this->tariff_cost,
            'bot_user_id' => $this->bot_user_id,
        ];
    }

    public function getSettings(): array
    {
        return [
            'tariff_cost' => $this->tariff_cost,
            'is_paid' => $this->is_paid
        ];
    }
}
