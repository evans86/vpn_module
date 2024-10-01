<?php

namespace App\Http\Controllers\Module;

use App\Models\Server\Server;
use App\Services\Panel\marzban\PanelService;
use App\Services\Server\ServerStrategy;
use App\Services\Server\vdsina\ServerService;

class ServerController
{
    public function index()
    {
        return view('module.server.index');
    }

    public function create()
    {
        $strategy = new ServerStrategy(Server::VDSINA);
        $strategy->create();

        return redirect()->route('module.server.index');
    }
}
