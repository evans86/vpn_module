<?php

namespace App\Http\Controllers\Module;

use App\Dto\Pack\PackFactory;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Pack\Pack;
use App\Services\Cloudflare\CloudflareService;
use App\Services\Key\KeyActivateService;
use App\Services\Pack\PackSalesmanService;
use App\Services\Pack\PackService;
use App\Services\Salesman\SalesmanService;
use App\Services\Server\vdsina\VdsinaService;

class TestController
{
    public function salesman()
    {
        $salesmanService = new SalesmanService();
        $salesmanService->create(123456778, 'FirstSalesman', 'TGToken', '@bot_salesman');

        return view('module.test.salesman');
    }

    public function pack()
    {
//        $packService = new PackService();
//        $packDto = PackFactory::fromEntity();
//        $packDto->price = 777;
//        $packDto->period = 25;
//        $packDto->count = 1000;
//        $packService->updateSettings($packDto);

        $packSalesmanService = new PackSalesmanService();
        $packSalesmanService->success(1);

//        dd(KeyActivate::query()->first());

        return view('module.test.pack');
    }

    public function packSalesman()
    {
        $packSalesmanService = new PackSalesmanService();
        $packSalesmanService->create(1, 1);

//        $packSalesmanService = new PackSalesmanService();
//        $packSalesmanService->success(1);


        return view('module.test.pack-salesman');
    }

    public function keyActivate()
    {
        $key_activate_service = new KeyActivateService();
        $key_activate_service->activation('05640a53-e567-493d-b28c-b11d423a127d', 987654321);

        return view('module.test.key-activate');
    }

    public function keyUser()
    {
        return view('module.test.key-user');
    }
}
