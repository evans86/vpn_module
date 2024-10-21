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

//        $strategy = new PanelStrategy(Panel::MARZBAN);
//        $strategy->addServerUser(16);

        $strategy = new PanelStrategy(Panel::MARZBAN);
        $strategy->checkOnline(16, '14252a15-865f-4241-944d-38fdb2e57c64');

//        $strategy = new PanelStrategy(Panel::MARZBAN);
//        $strategy->deleteServerUser(16, '2e9af71c-45f7-4194-81f9-6976ee077f80');

        return redirect()->route('module.panel.index');
    }
}
