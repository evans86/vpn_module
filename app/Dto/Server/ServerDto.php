<?php

namespace App\Dto\Server;

class ServerDto
{
    public int $id;
    public ?string $provider_id; //идентификатор провайдера
    public ?string $ip;
    public ?string $login;
    public ?string $password;
    public ?string $name; //имя из панели
    public ?string $dns_record_id;
    public string $host; //строка ssh подключения
    public string $provider; // vdsina
    public int $location_id; // ru/ne
    public int $server_status;
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
            'dns_record_id' => $this->dns_record_id,
            'host' => $this->host,
            'provider' => $this->provider,
            'location_id' => $this->location_id,
            'server_status' => $this->server_status,
            'is_free' => $this->is_free
        ];
    }
}
