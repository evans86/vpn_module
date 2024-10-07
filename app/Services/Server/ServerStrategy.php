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

    public function configure(int $location_id, string $provider, bool $isFree): void
    {
        $this->strategy->configure($location_id, $provider, $isFree);
    }

    public function checkStatus()
    {
        $this->strategy->checkStatus();
    }

    public function setPanel(int $server_id, string $panel)
    {
        $this->strategy->setPanel($server_id, $panel);
    }
}
