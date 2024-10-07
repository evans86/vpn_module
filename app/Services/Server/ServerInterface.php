<?php

namespace App\Services\Server;

use App\Dto\Server\ServerDto;

interface ServerInterface
{
    public function configure(int $location_id, string $provider, bool $isFree): void;

    public function checkStatus(): void;

    public function setPanel(int $server_id, string $panel): void;
}
