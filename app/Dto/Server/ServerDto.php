<?php

namespace App\Dto\Server;

class ServerDto
{
    public int $id;
    public string $provider_id; //идентификатор провайдера
    public int $ip;
    public string $login;
    public string $password;
    public string $name; //имя из панели
    public string $host; //строка ssh подключения
    public string $provider; // vdsina
    public string $panel; // Marzban
    public int $location_id; // ru/ne
    public string $panel_adress;
    public string $panel_login;
    public string $panel_password;
    public string $panel_key;
    public bool $is_free; // платный или бесплатный тариф


    public function getArray(): array
    {
        return [
            'id' => $this->id,
            'provider_id' => $this->provider_id,
            'ip' => $this->ip,
            'login' => $this->login,
            'password' => $this->password,
            'name' => $this->name,
            'host' => $this->host,
            'provider' => $this->provider,
            'panel' => $this->panel,
            'location_id' => $this->location_id,
            'panel_adress' => $this->panel_adress,
            'panel_login' => $this->panel_login,
            'panel_password' => $this->panel_password,
            'panel_key' => $this->panel_key,
            'is_free' => $this->is_free
        ];
    }
}
