<?php

namespace App\Http\Controllers\Module;

use App\Services\Server\ServerService;

class TestController
{
    public function index()
    {
        $server_service = new ServerService();
        $server_service->getAccount();

        return view('module.test.index');
    }
}
