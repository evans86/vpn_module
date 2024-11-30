<?php

namespace App\Services\Server\strategy;

use App\Models\Server\Server;

class ServerStrategyFactory
{
    /**
     * Create server strategy based on provider
     *
     * @param string $provider
     * @return ServerMainStrategy
     * @throws \InvalidArgumentException
     */
    public static function create(string $provider): ServerMainStrategy
    {
        switch ($provider) {
            case Server::VDSINA:
                return new ServerVdsinaStrategy();
            default:
                throw new \InvalidArgumentException('Unsupported provider: ' . $provider);
        }
    }
}
