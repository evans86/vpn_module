<?php

namespace App\Http\Controllers\Module;

use App\Models\Server\Server;
use App\Services\Server\ServerStrategy;

class ServerController
{
    public function index()
    {
        return view('module.server.index');
    }

    public function create()
    {
        $strategy = new ServerStrategy(Server::VDSINA);
        $strategy->delete(25);
//        $strategy->configure(1, Server::VDSINA, false);

        return redirect()->route('module.server.index');
    }
}
