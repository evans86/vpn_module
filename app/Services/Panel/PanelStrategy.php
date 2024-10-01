<?php

namespace App\Services\Panel;

use App\Models\Panel\Panel;
use App\Services\Panel\strategy\PanelMarzbanStrategy;

class PanelStrategy
{
    public PanelInterface $strategy;

    public function __construct(string $provider)
    {
        switch ($provider) {
            case Panel::MARZBAN:
                $this->strategy = new PanelMarzbanStrategy();
                break;
            default:
                throw new \DomainException('panel strategy not found');
        }
    }

    public function create(): void
    {
        $this->strategy->create();
    }
}
