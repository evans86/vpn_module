<?php

namespace App\Http\Controllers\Module;

use App\Models\Panel\Panel;
use App\Services\Panel\marzban\PanelService;
use App\Services\Panel\PanelStrategy;

class PanelController
{
    public function index()
    {
        return view('module.panel.index');
    }

    public function create()
    {
        $strategy = new PanelStrategy(Panel::MARZBAN);
        $strategy->create();

        return redirect()->route('module.panel.index');
    }
}
