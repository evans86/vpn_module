<?php

namespace App\Repositories\Server;

use App\Models\Server\Server;
use Illuminate\Database\Eloquent\Builder;

class ServerRepository
{
    public function get(int $id): Builder
    {
        $server = Server::query()->where(['id' => $id])->limit(1);
        if (is_null($server))
            throw new \RuntimeException('Server Not Found');

        return $server;
    }

    public function save(Server $server): void
    {
        if (!$server->save())
            throw new \RuntimeException('Server Saving Error');
    }

    public function delete(Server $server): void
    {
        if (!$server->delete())
            throw new \RuntimeException('Server Delete Error');
    }

    public function getByProviderId(int $provider_id): Server
    {
        return Server::query()->where(['provider_id' => $provider_id])->first();
    }
}
