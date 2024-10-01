<?php

namespace App\Services\Server;

use App\Models\Server\Server;
use App\Services\Server\strategy\ServerVdsinaStrategy;

class ServerStrategy
{
    public ServerInterface $strategy;

    public function __construct(string $provider)
    {
        switch ($provider) {
            case Server::VDSINA:
                $this->strategy = new ServerVdsinaStrategy();
                break;
            default:
                throw new \DomainException('server strategy not found');
        }
    }

    public function create(): void
    {
        $this->strategy->create();
    }
}
