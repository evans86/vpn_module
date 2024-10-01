<?php

namespace App\Services\Server\strategy;

use App\Models\Server\Server;
use App\Services\Server\ServerInterface;
use App\Services\Server\vdsina\ServerService;

class ServerVdsinaStrategy extends ServerMainStrategy implements ServerInterface
{
    public function create(): void
    {
        $vdsinaService = new ServerService();
        $server = $vdsinaService->create();
    }
}
