<?php

namespace App\Services\Panel\strategy;

use App\Services\Panel\marzban\PanelService;
use App\Services\Panel\PanelInterface;

class PanelMarzbanStrategy extends PanelMainStrategy implements PanelInterface
{
    public function create(): void
    {
        $marzbanServer = new PanelService();
        $marzbanServer->create(18);
    }
}
