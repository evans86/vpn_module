<?php

namespace App\Services\Server;

use App\Models\Server\Server;

interface ServerInterface
{
    /**
     * @param int $location_id
     * @param string $provider
     * @param bool $isFree
     * @return Server
     */
    public function configure(int $location_id, string $provider, bool $isFree): Server;

    /**
     * @return void
     */
    public function checkStatus(): void;

    /**
     * @param int $server_id
     * @param string $panel
     * @return void
     */
    public function setPanel(int $server_id, string $panel): void;

    /**
     * Удаление сервера
     *
     * @param Server $server
     * @return void
     * @throws \Exception
     */
    public function delete(Server $server): void;
}
