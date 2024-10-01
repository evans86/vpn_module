<?php

namespace App\Http\Controllers\Module;

use App\Services\Cloudflare\CloudflareService;
use App\Services\Server\vdsina\ServerService;

class TestController
{
    public function index()
    {
        $server_service = new ServerService();
        $server_service->create();

        return view('module.test.index');
    }

    public function panel()
    {
        $panel_service = new CloudflareService();
        $panel_service->testAPI();

        return view('module.test.panel');
    }
}
