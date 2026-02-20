<?php

namespace App\Dto\Bot;

class BotModuleDto
{
    public int $id;
    public string $public_key;
    public string $private_key;
    public int $bot_id;
    public int $category_id;
    public ?int $version;
    public int $is_paid;
    public int $free_show;
    public ?string $secret_user_key;
    public ?string $tariff_cost;
    public ?string $vpn_instructions;
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
//            'vpn_instructions' => $this->vpn_instructions,
            'bot_user_id' => $this->bot_user_id,
            'free_show' => $this->free_show,
        ];
    }

    public function getSettings(): array
    {
        return [
            'tariff_cost' => $this->tariff_cost,
            'is_paid' => $this->is_paid,
            'free_show' => $this->free_show
        ];
    }
}
