<?php

namespace App\Http\Controllers\Module;

use App\Models\Server\Server;
use App\Services\Server\ServerStrategy;

class ServerController
{
    public function index()
    {
        $servers = Server::orderBy('id', 'desc')->limit(1000)->Paginate(10);

        return view('module.server.index', compact('servers'));
    }

    public function create()
    {
        $strategy = new ServerStrategy(Server::VDSINA);
        $strategy->delete(25);
//        $strategy->configure(1, Server::VDSINA, false);

        return redirect()->route('module.server.index');
    }
}
