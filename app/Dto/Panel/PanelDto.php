<?php

namespace App\Dto\Panel;

class PanelDto
{
    public int $id;
    public int $server_id;
    public string $panel; // Marzban
    public string $panel_adress;
    public string $panel_login;
    public string $panel_password;
    public int $panel_status;
    public ?string $auth_token;
    public ?int $token_died_time;

    public function createArray(): array
    {
        return [
            'server_id' => $this->server_id,
            'panel' => $this->panel,
            'panel_status' => $this->panel_status
        ];
    }
    public function getArray(): array
    {
        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'panel' => $this->panel,
            'panel_adress' => $this->panel_adress,
            'panel_login' => $this->panel_login,
            'panel_password' => $this->panel_password,
            'panel_status' => $this->panel_status,
            'auth_token' => $this->auth_token,
            'token_died_time' => $this->token_died_time,
        ];
    }
}
