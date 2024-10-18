<?php

namespace App\Http\Controllers\Module;

use App\Models\Panel\Panel;
use App\Services\Panel\marzban\MarzbanService;
use App\Services\Panel\PanelStrategy;

class PanelController
{
    public function index()
    {
        return view('module.panel.index');
    }

    public function create()
    {
//        $strategy = new PanelStrategy(Panel::MARZBAN);
//        $strategy->create(23);

//        $service = new MarzbanService();
//        $service->updateConfiguration(16);

        //проверить обновление токена +
//        $strategy = new PanelStrategy(Panel::MARZBAN);
//        $strategy->addServerUser(16);

        $strategy = new PanelStrategy(Panel::MARZBAN);
        $strategy->deleteServerUser(16, '13e048b5-910d-4eca-ae08-ee50abf20d22');

        return redirect()->route('module.panel.index');
    }
}
