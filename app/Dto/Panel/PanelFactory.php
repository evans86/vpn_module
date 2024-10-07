<?php

namespace App\Dto\Panel;

use App\Models\Panel\Panel;

class PanelFactory
{
    public static function fromEntity(Panel $server): PanelDto
    {
        $dto = new PanelDto();
        $dto->id = $server->id;
        $dto->server_id = $server->server_id;
        $dto->panel = $server->panel;
        $dto->panel_adress = $server->panel_adress;
        $dto->panel_login = $server->panel_login;
        $dto->panel_password = $server->panel_password;
        $dto->panel_status = $server->panel_status;
        $dto->auth_token = $server->auth_token;
        $dto->token_died_time = $server->token_died_time;

        return $dto;
    }
}
