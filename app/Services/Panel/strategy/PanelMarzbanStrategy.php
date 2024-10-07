<?php

namespace App\Services\Panel\strategy;

use App\Services\Panel\marzban\MarzbanService;
use App\Services\Panel\PanelInterface;

class PanelMarzbanStrategy extends PanelMainStrategy implements PanelInterface
{
    /**
     * Создание панели
     *
     * @param int $server_id
     * @return void
     */
    public function create(int $server_id): void
    {
        $marzbanServer = new MarzbanService();
        $marzbanServer->create($server_id);
    }
}
