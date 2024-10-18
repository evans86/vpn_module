<?php

namespace App\Services\Server;

interface ServerInterface
{
    /**
     * @param int $location_id
     * @param string $provider
     * @param bool $isFree
     * @return void
     */
    public function configure(int $location_id, string $provider, bool $isFree): void;

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
     * @param int $server_id
     * @return void
     */
    public function delete(int $server_id): void;
}
